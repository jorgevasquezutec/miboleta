<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewSignatureCertificateRequest;
use App\Http\Requests\StoreTenantSignatureCertificateRequest;
use App\Models\SignatureSettings;
use App\Models\Tenant;
use App\Models\TenantSignatureCertificate;
use App\Services\Signature\CertificateInspector;
use App\Services\SignatureCertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * @OA\Tag(
 *     name="Certificados de Firma por Empresa",
 *     description="Certificado de firma digital propio de cada empresa (fallback: el global). Solo root."
 * )
 *
 * Endpoints SOLO ROOT. Nunca exponen la password ni la ruta del binario.
 */
class TenantSignatureCertificateController extends Controller
{
    public function __construct(
        protected SignatureCertificateService $service,
        protected CertificateInspector $inspector
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/signature/tenants",
     *     tags={"Certificados de Firma por Empresa"},
     *     summary="Listar el estado del certificado de firma de cada empresa",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="only_warnings", in="query", @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="page", in="query", description="Página (>=1, default 1)", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Filas por página (1..100, default 10)", @OA\Schema(type="integer", minimum=1, maximum=100, default=10)),
     *     @OA\Response(response=200, description="Listado paginado con meta (total, current_page, last_page, per_page y conteos globales)"),
     *     @OA\Response(response=403, description="No autorizado - Solo root")
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $search = $request->query('search');
            $all = $this->service->listTenantCertificates($user, ['search' => is_string($search) ? $search : null]);
            $filtered = $request->boolean('only_warnings')
                ? $all->filter(fn (array $i) => count($i['warnings']) > 0)->values()
                : $all;

            $pageParam = $request->query('page');
            $perPageParam = $request->query('per_page');
            $page = is_numeric($pageParam) ? max(1, (int) $pageParam) : 1;
            $perPage = is_numeric($perPageParam) ? min(100, max(1, (int) $perPageParam)) : 10;
            $total = $filtered->count();
            $lastPage = max(1, (int) ceil($total / $perPage));

            // Paginación en memoria (los avisos se calculan en PHP).
            // Los conteos with_* se calculan ANTES de only_warnings, sobre el conjunto completo.
            return response()->json([
                'data' => $filtered->values()->slice(($page - 1) * $perPage, $perPage)->values(),
                'meta' => [
                    'total' => $total,
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'with_own_certificate' => $all->where('has_own_certificate', true)->count(),
                    'with_warnings' => $all->filter(fn (array $i) => count($i['warnings']) > 0)->count(),
                    'global_has_certificate' => SignatureSettings::current()->hasCertificate(),
                ],
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            Log::error('[TenantSignatureCertificateController] Error al listar certificados de empresa', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Error al obtener los certificados de firma'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/signature/tenants/{tenantId}/certificate",
     *     tags={"Certificados de Firma por Empresa"},
     *     summary="Estado del certificado de firma de una empresa",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="tenantId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Mismo item que GET /signature/tenants"),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=404, description="Empresa no encontrada")
     * )
     */
    public function show(Request $request, int $tenantId)
    {
        try {
            // 403 antes que 404: no revelar qué empresas existen a no-root.
            $this->service->ensureRoot($request->user());

            $tenant = Tenant::find($tenantId);

            if (!$tenant) {
                return $this->tenantNotFound();
            }

            return response()->json(['data' => $this->item($tenant)]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            Log::error('[TenantSignatureCertificateController] Error al obtener certificado de empresa', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Error al obtener el certificado de firma'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/signature/certificate/preview",
     *     tags={"Certificados de Firma por Empresa"},
     *     summary="Vista previa de un certificado (no guarda nada)",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"certificate", "password"},
     *                 @OA\Property(property="certificate", type="string", format="binary"),
     *                 @OA\Property(property="password", type="string"),
     *                 @OA\Property(property="ruc", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Metadatos del certificado y avisos"),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=422, description="Archivo o contraseña inválidos")
     * )
     */
    public function preview(PreviewSignatureCertificateRequest $request)
    {
        $validated = $request->validated();

        try {
            $info = $this->inspector->inspect($request->file('certificate'), $validated['password']);
            $ruc = $validated['ruc'] ?? null;

            // Modelo en memoria (sin guardar) para reutilizar rucMatches/warnings.
            $record = new TenantSignatureCertificate([
                'certificate_ruc' => $info->ruc,
                'certificate_expires_at' => $info->expiresAt,
            ]);

            return response()->json(['data' => [
                'certificate_subject' => $info->subject,
                'certificate_ruc' => $info->ruc,
                'certificate_organization' => $info->organization,
                'certificate_expires_at' => $record->certificate_expires_at,
                'ruc_mismatch' => $record->rucMatches($ruc) === false,
                'warnings' => $record->warnings($ruc),
            ]]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[TenantSignatureCertificateController] Error en vista previa de certificado', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Error al procesar el certificado de firma'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/signature/tenants/{tenantId}/certificate",
     *     tags={"Certificados de Firma por Empresa"},
     *     summary="Cargar/reemplazar el certificado de firma de una empresa",
     *     description="Nunca se rechaza por un RUC distinto: responde 201 con aviso.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="tenantId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"certificate", "password"},
     *                 @OA\Property(property="certificate", type="string", format="binary"),
     *                 @OA\Property(property="password", type="string"),
     *                 @OA\Property(property="tsa_url", type="string", nullable=true, description="TSA propia de la empresa (opcional)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Certificado cargado"),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=404, description="Empresa no encontrada"),
     *     @OA\Response(response=422, description="Archivo o contraseña inválidos")
     * )
     */
    public function store(StoreTenantSignatureCertificateRequest $request, int $tenantId)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return $this->tenantNotFound();
        }

        $validated = $request->validated();

        try {
            $record = $this->service->storeTenantCertificate(
                $request->user(),
                $tenant,
                $request->file('certificate'),
                $validated['password'],
                $validated['tsa_url'] ?? null
            );

            $item = $this->item($tenant);

            $message = $item['ruc_mismatch']
                ? "Certificado cargado con aviso: el RUC del certificado ({$record->certificate_ruc}) no coincide con el RUC de la empresa ({$tenant->ruc})."
                : 'Certificado de la empresa cargado exitosamente.';

            return response()->json(['message' => $message, 'data' => $item], 201);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[TenantSignatureCertificateController] Error al cargar certificado de empresa', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Error al procesar el certificado de firma'], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/signature/tenants/{tenantId}/certificate",
     *     tags={"Certificados de Firma por Empresa"},
     *     summary="Eliminar el certificado de firma propio de una empresa",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="tenantId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Eliminado; se usará el global"),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=404, description="Empresa no encontrada"),
     *     @OA\Response(response=422, description="La empresa no tiene certificado propio")
     * )
     */
    public function destroy(Request $request, int $tenantId)
    {
        try {
            // 403 antes que 404: no revelar qué empresas existen a no-root.
            $this->service->ensureRoot($request->user());

            $tenant = Tenant::find($tenantId);

            if (!$tenant) {
                return $this->tenantNotFound();
            }

            $this->service->deleteTenantCertificate($request->user(), $tenant);

            return response()->json([
                'message' => 'Certificado de la empresa eliminado. Se usará el certificado global.',
                'data' => $this->item($tenant),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[TenantSignatureCertificateController] Error al eliminar certificado de empresa', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Error al eliminar el certificado de firma'], 500);
        }
    }

    private function item(Tenant $tenant): array
    {
        $tenant = Tenant::with('signatureCertificate.uploadedBy')->find($tenant->id);

        return $this->service->transformTenantCertificate(
            $tenant,
            SignatureSettings::current()->hasCertificate()
        );
    }

    private function tenantNotFound()
    {
        return response()->json(['message' => 'Empresa no encontrada.'], 404);
    }
}
