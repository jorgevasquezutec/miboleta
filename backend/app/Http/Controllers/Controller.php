<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @OA\Info(
 *     title="MiBoleta API",
 *     version="1.0.0",
 *     description="API para gestión de documentos y vacaciones",
 *     @OA\Contact(
 *         email="admin@miboleta.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token de autenticación Sanctum"
 * )
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string", example="Juan"),
 *     @OA\Property(property="last_name", type="string", example="Pérez"),
 *     @OA\Property(property="full_name", type="string", example="Juan Pérez"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="avatar_url", type="string", nullable=true),
 *     @OA\Property(property="document_type", type="string", example="DNI"),
 *     @OA\Property(property="document_text", type="string", example="12345678"),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}),
 *     @OA\Property(property="role", type="string", example="client"),
 *     @OA\Property(property="roles", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Tenant",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string", example="Corporación ABC"),
 *     @OA\Property(property="ruc", type="string", example="20123456789"),
 *     @OA\Property(property="business_name", type="string", example="ABC S.A.C."),
 *     @OA\Property(property="address", type="string", nullable=true),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="logo_url", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Document",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="tenant_id", type="string"),
 *     @OA\Property(property="user_id", type="string", nullable=true),
 *     @OA\Property(property="employee_document_number", type="string", example="12345678"),
 *     @OA\Property(property="doc_type_id", type="integer"),
 *     @OA\Property(property="document_type", type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="display_name", type="string")
 *     ),
 *     @OA\Property(property="period", type="string", example="2024-01"),
 *     @OA\Property(property="file_path", type="string"),
 *     @OA\Property(property="file_size", type="integer"),
 *     @OA\Property(property="original_name", type="string"),
 *     @OA\Property(property="status", type="string", enum={"pending", "signed", "orphan", "active"}),
 *     @OA\Property(property="requires_signature", type="boolean"),
 *     @OA\Property(property="signed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="DocumentType",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string", example="boleta"),
 *     @OA\Property(property="display_name", type="string", example="Boleta de Pago"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="requires_signature", type="boolean"),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="DocumentBatch",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="tenant_id", type="string"),
 *     @OA\Property(property="uploaded_by", type="string"),
 *     @OA\Property(property="doc_type_id", type="integer"),
 *     @OA\Property(property="document_type", type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="display_name", type="string")
 *     ),
 *     @OA\Property(property="period", type="string", example="2024-01"),
 *     @OA\Property(property="filename", type="string"),
 *     @OA\Property(property="file_path", type="string"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}),
 *     @OA\Property(property="total_files", type="integer"),
 *     @OA\Property(property="processed_files", type="integer"),
 *     @OA\Property(property="failed_files", type="integer"),
 *     @OA\Property(property="orphan_files", type="integer"),
 *     @OA\Property(property="progress", type="integer", description="Porcentaje de progreso 0-100"),
 *     @OA\Property(property="errors", type="array", @OA\Items(type="object"), nullable=true),
 *     @OA\Property(property="processed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Role",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string", enum={"root", "admin", "client"}),
 *     @OA\Property(property="display_name", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="permissions", type="object", nullable=true)
 * )
 */
abstract class Controller
{
    use AuthorizesRequests;
}
