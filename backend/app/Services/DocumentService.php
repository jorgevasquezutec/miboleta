<?php

namespace App\Services;

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\UnauthorizedAccessException;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
//Log
use Illuminate\Support\Facades\Log;

class DocumentService
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * Get documents with filters based on user role.
     *
     * @param User $user
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getDocuments(User $user, array $filters = []): LengthAwarePaginator
    {
        $role = $user->getCurrentRole();

        $query = Document::with(['documentType', 'user:id,name,last_name,document_text', 'batch:id,period,original_filename'])
            ->orderBy('created_at', 'desc');

        // Apply role-based filtering
        $query = $this->applyRoleFilters($query, $user, $role, $filters);

        // Apply optional filters
        $query = $this->applyOptionalFilters($query, $filters);


        // $queryLog = $query->toSql();
        // $bindings = $query->getBindings();
        // for ($i = 0; $i < count($bindings); $i++) {
        //     $queryLog = str_replace('?' . $i, $bindings[$i], $queryLog);
        // }
        // Log::info($queryLog);


        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Get a single document with access check.
     *
     * @param int $id
     * @param User $user
     * @return Document
     * @throws DocumentNotFoundException
     * @throws UnauthorizedAccessException
     */
    public function getDocument(int $id, User $user): Document
    {
        $document = Document::with(['documentType', 'user', 'batch', 'uploader'])->find($id);

        if (!$document) {
            throw new DocumentNotFoundException("Documento no encontrado");
        }

        if (!$this->canAccessDocument($user, $document)) {
            throw new UnauthorizedAccessException("No autorizado para ver este documento");
        }

        return $document;
    }

    /**
     * Get orphan documents (documents without assigned user).
     *
     * @param User $user
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getOrphanDocuments(User $user, array $filters = []): LengthAwarePaginator
    {
        $tenantId = $filters['tenant_id'] ?? $user->tenants->first()?->id;

        $query = Document::orphan()
            ->with(['documentType', 'batch:id,period,original_filename'])
            ->orderBy('created_at', 'desc');

        // isRoot() en vez de getCurrentRole() !== 'root': mismo resultado pero
        // determinístico (root es global; el respaldo global de roles no).
        if ($tenantId && !$user->isRoot()) {
            $query->where('tenant_id', $tenantId);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Assign an orphan document to a user.
     *
     * @param Document $document
     * @param User $targetUser
     * @return Document
     * @throws \InvalidArgumentException
     */
    public function assignOrphanDocument(Document $document, User $targetUser): Document
    {
        if (!$document->isOrphan()) {
            throw new \InvalidArgumentException('El documento no es huérfano');
        }

        $document->assignToUser($targetUser);

        return $document->fresh(['documentType', 'user']);
    }

    /**
     * Download a document.
     *
     * @param int $id
     * @param User $user
     * @return array ['path' => string, 'filename' => string]
     * @throws DocumentNotFoundException
     * @throws UnauthorizedAccessException
     */
    public function downloadDocument(int $id, User $user): array
    {
        $result = $this->resolveDownloadableFile($id, $user);

        $this->auditService->logDocumentDownloaded($id);

        return $result;
    }

    /**
     * Preview a document (inline).
     *
     * @param int $id
     * @param User $user
     * @return array ['path' => string, 'filename' => string]
     * @throws DocumentNotFoundException
     * @throws UnauthorizedAccessException
     */
    public function previewDocument(int $id, User $user): array
    {
        $result = $this->resolveDownloadableFile($id, $user);

        $this->auditService->logDocumentViewed($id);

        return $result;
    }

    /**
     * Resuelve la ruta y nombre del archivo de un documento tras validar
     * existencia, acceso y presencia física. Núcleo compartido por
     * downloadDocument()/previewDocument(), que solo difieren en el evento de
     * auditoría (descarga vs. visualización).
     *
     * @throws DocumentNotFoundException
     * @throws UnauthorizedAccessException
     */
    protected function resolveDownloadableFile(int $id, User $user): array
    {
        $document = Document::find($id);

        if (!$document) {
            throw new DocumentNotFoundException("Documento no encontrado");
        }

        if (!$this->canAccessDocument($user, $document)) {
            throw new UnauthorizedAccessException("No autorizado para descargar este documento");
        }

        if (!$document->fileExists()) {
            throw new DocumentNotFoundException("Archivo no encontrado");
        }

        return [
            'path' => Storage::disk('documents')->path($document->file_path),
            'filename' => $document->original_name,
        ];
    }

    /**
     * Delete a document.
     *
     * @param int $id
     * @param User $user
     * @param bool $deleteFile Whether to also delete the physical file
     * @return bool
     * @throws DocumentNotFoundException
     * @throws UnauthorizedAccessException
     */
    public function deleteDocument(int $id, User $user, bool $deleteFile = false): bool
    {
        $document = Document::find($id);

        if (!$document) {
            throw new DocumentNotFoundException("Documento no encontrado");
        }

        // Autorizado contra la empresa DEL DOCUMENTO (no el rol global, que
        // permitía borrar en una empresa usando el rol de otra).
        if (!$user->can('documents.delete', $document->tenant_id)) {
            throw new UnauthorizedAccessException("No autorizado para eliminar documentos");
        }

        if ($deleteFile && $document->fileExists()) {
            Storage::disk('documents')->delete($document->file_path);
        }

        $snapshot = [
            'original_name' => $document->original_name,
            'tenant_id' => $document->tenant_id,
            'user_id' => $document->user_id,
        ];

        $deleted = $document->delete();

        if ($deleted) {
            $this->auditService->logDocumentDeleted($id, $snapshot);
        }

        return $deleted;
    }

    /**
     * Check if user can access a document.
     *
     * @param User $user
     * @param Document $document
     * @return bool
     */
    public function canAccessDocument(User $user, Document $document): bool
    {
        // El rol se evalúa dentro de la empresa DEL DOCUMENTO, no con el rol
        // global ni con la empresa "activa" de la sesión. Antes se usaba
        // getCurrentRole() (respaldo global = unión de los roles del usuario en
        // TODAS sus empresas): quien fuera admin en la empresa A podía leer
        // documentos ajenos de la empresa B donde solo es client, porque el rol
        // global resolvía 'admin' y bastaba con pertenecer a B.
        $tenantId = $document->tenant_id;

        // Documento propio: requiere poder ver los documentos personales.
        if ($document->user_id === $user->id) {
            return $user->can('documents.view_own', $tenantId);
        }

        // Documento de otra persona: requiere ver los documentos de la empresa.
        return $user->can('documents.view_org', $tenantId);
    }

    /**
     * Apply role-based filters to query.
     *
     * @param Builder $query
     * @param User $user
     * @param string $role
     * @param array $filters
     * @return Builder
     */
    /**
     * LIMITACIÓN CONOCIDA (preexistente, ver plan de autorización, Riesgo #2):
     * $role llega desde getDocuments() vía getCurrentRole() SIN empresa, o sea
     * el respaldo global (unión de los roles del usuario en todas sus empresas).
     * Para un usuario con roles distintos por empresa el filtrado de filas puede
     * ser más permisivo de lo debido (p. ej. client en la empresa A y admin en
     * la B ⇒ el rol global resuelve 'admin' y no se aplica el filtro
     * "solo mis documentos" al listar A).
     *
     * No se corrige en este paso porque el listado admite filtrar por VARIAS
     * empresas a la vez (X-Tenant-Ids en modo 'selected'), y ahí no existe un
     * rol único válido para toda la consulta: haría falta filtrar por fila según
     * el rol en la empresa de cada documento. Los checks sobre un documento
     * CONCRETO (canAccessDocument/deleteDocument) sí están ya scopeados a la
     * empresa del documento.
     */
    protected function applyRoleFilters(Builder $query, User $user, string $role, array $filters): Builder
    {
        $myDocuments = $filters['my_documents'] ?? false;
        $tenantId = $filters['tenant_id'] ?? null;

        if ($myDocuments) {
            // Show only user's own documents
            $query->where('user_id', $user->id);
            // tenant_id filter is automatic via global scope
        } elseif ($role === 'client') {
            // Clients only see their own documents
            $query->where('user_id', $user->id);
            // tenant_id filter is automatic via global scope
        } elseif ($role === 'root' && $tenantId) {
            // Root users can manually filter by specific tenant
            $query->where('tenant_id', $tenantId);
        }
        // For admin/root (without tenant filter): show all documents (filtered by tenant via global scope)

        return $query;
    }

    /**
     * Apply optional filters to query.
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    protected function applyOptionalFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['doc_type_id'])) {
            $query->where('doc_type_id', $filters['doc_type_id']);
        }

        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('employee_document_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Per-user document counts for the employee dashboard summary cards.
     *
     * Applies the SAME optional filters as the documents list (date range, type,
     * search, period) EXCEPT status, so the cards show the status breakdown
     * (total / signed / pending) within the currently filtered scope and stay
     * consistent with the list.
     */
    public function getMyDocumentStats(User $user, array $filters = []): array
    {
        $base = Document::query()->where('user_id', $user->id);

        $filtersNoStatus = $filters;
        unset($filtersNoStatus['status']);
        $this->applyOptionalFilters($base, $filtersNoStatus);

        return [
            'total' => (clone $base)->count(),
            'signed' => (clone $base)->where('status', 'signed')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
        ];
    }
}
