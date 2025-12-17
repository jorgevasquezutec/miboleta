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
        $role = $user->getCurrentRole();
        $tenantId = $filters['tenant_id'] ?? $user->tenants->first()?->id;

        $query = Document::orphan()
            ->with(['documentType', 'batch:id,period,original_filename'])
            ->orderBy('created_at', 'desc');

        if ($tenantId && $role !== 'root') {
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
        return $this->downloadDocument($id, $user);
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

        $role = $user->getCurrentRole();
        if ($role === 'client') {
            throw new UnauthorizedAccessException("No autorizado para eliminar documentos");
        }

        if ($deleteFile && $document->fileExists()) {
            Storage::disk('documents')->delete($document->file_path);
        }

        return $document->delete();
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
        $role = $user->getCurrentRole();

        // Root can access everything
        if ($role === 'root') {
            return true;
        }

        // Admin can access documents in their tenant
        if ($role === 'admin') {
            return $user->tenants->pluck('id')->contains($document->tenant_id);
        }

        // Client can only access their own documents
        return $document->user_id === $user->id;
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
    protected function applyRoleFilters(Builder $query, User $user, string $role, array $filters): Builder
    {
        $myDocuments = $filters['my_documents'] ?? false;
        // ✅ tenant_id filtering removed - now handled by TenantFilterScope global scope

        if ($myDocuments) {
            // Show only user's own documents
            $query->where('user_id', $user->id);
            // tenant_id filter is automatic via global scope
        } elseif ($role === 'client') {
            // Clients only see their own documents
            $query->where('user_id', $user->id);
            // tenant_id filter is automatic via global scope
        }
        // For admin/root: show all documents (filtered by tenant via global scope)

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
}
