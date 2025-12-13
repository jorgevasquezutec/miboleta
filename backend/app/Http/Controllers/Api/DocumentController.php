<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    /**
     * Lista los documentos según el rol del usuario
     * - Root/Admin: todos los documentos del tenant
     * - Client: solo sus documentos
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        $query = Document::with(['documentType', 'user:id,name,last_name,document_text', 'batch:id,period,original_filename'])
            ->orderBy('created_at', 'desc');

        // Si se solicita "my_documents", solo mostrar documentos del usuario logueado (sin importar rol)
        if ($request->boolean('my_documents')) {
            $query->where('user_id', $user->id);

            // También filtrar por tenant si el usuario no es root
            if ($role !== 'root') {
                $tenantId = $request->header('X-Tenant-Id') ?? $user->tenants->first()?->id;
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
            }
        }
        // Filtrar según rol (solo si no es my_documents)
        elseif ($role === 'client') {
            $query->where('user_id', $user->id);

            // También filtrar por tenant para clientes
            $tenantId = $request->header('X-Tenant-Id') ?? $user->tenants->first()?->id;
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
        } else {
            // Admin ve documentos de su tenant, Root ve todos
            $tenantId = $request->header('X-Tenant-Id') ?? $user->tenants->first()?->id;
            if ($tenantId && $role !== 'root') {
                $query->where('tenant_id', $tenantId);
            }
        }

        // Filtros opcionales
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('doc_type_id')) {
            $query->where('doc_type_id', $request->doc_type_id);
        }

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_document_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        // Filtro por rango de fechas
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 15);
        $documents = $query->paginate($perPage);

        return response()->json([
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        ]);
    }

    /**
     * Muestra un documento específico
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        $document = Document::with(['documentType', 'user', 'batch', 'uploader'])
            ->findOrFail($id);

        // Verificar acceso
        if ($role === 'client' && $document->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'data' => $document
        ]);
    }

    /**
     * Descarga un documento
     */
    public function download(int $id): BinaryFileResponse|JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        $document = Document::findOrFail($id);

        // Verificar acceso
        if ($role === 'client' && $document->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if (!$document->fileExists()) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        $fullPath = Storage::disk('documents')->path($document->file_path);

        return response()->download($fullPath, $document->original_name, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Preview un documento (inline, sin descargar)
     */
    public function preview(int $id): BinaryFileResponse|JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        $document = Document::findOrFail($id);

        // Verificar acceso
        if ($role === 'client' && $document->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if (!$document->fileExists()) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        $fullPath = Storage::disk('documents')->path($document->file_path);

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
        ]);
    }


    /**
     * Lista documentos huérfanos (solo admin/root)
     */
    public function orphans(Request $request): JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        if ($role === 'client') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $tenantId = $request->header('X-Tenant-Id') ?? $user->tenants->first()?->id;

        $query = Document::orphan()
            ->with(['documentType', 'batch:id,period,original_filename'])
            ->orderBy('created_at', 'desc');

        if ($tenantId && $role !== 'root') {
            $query->where('tenant_id', $tenantId);
        }

        $perPage = $request->get('per_page', 15);
        $documents = $query->paginate($perPage);

        return response()->json([
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        ]);
    }

    /**
     * Asigna un documento huérfano a un usuario
     */
    public function assignOrphan(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        if ($role === 'client') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $document = Document::findOrFail($id);

        if (!$document->isOrphan()) {
            return response()->json(['error' => 'El documento no es huérfano'], 400);
        }

        $targetUser = User::findOrFail($request->user_id);
        $document->assignToUser($targetUser);

        return response()->json([
            'message' => 'Documento asignado correctamente',
            'data' => $document->fresh(['documentType', 'user'])
        ]);
    }

    /**
     * Elimina un documento (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        if ($role === 'client') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $document = Document::findOrFail($id);

        // Opcional: eliminar archivo físico
        // Storage::disk('documents')->delete($document->file_path);

        $document->delete();

        return response()->json([
            'message' => 'Documento eliminado correctamente'
        ]);
    }
}
