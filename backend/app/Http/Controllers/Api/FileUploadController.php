<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteFileRequest;
use App\Http\Requests\UploadTenantLogoRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Archivos",
 *     description="Gestión de carga y eliminación de archivos"
 * )
 */
class FileUploadController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/upload/tenant-logo",
     *     tags={"Archivos"},
     *     summary="Subir logo de tenant",
     *     description="Sube una imagen para usar como logo de una organización",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"logo"},
     *                 @OA\Property(property="logo", type="string", format="binary", description="Imagen (jpg, png, svg, max 2MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Logo subido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="url", type="string", example="tenants/logos/abc123.png"),
     *             @OA\Property(property="full_url", type="string", example="http://localhost/storage/tenants/logos/abc123.png"),
     *             @OA\Property(property="path", type="string", example="tenants/logos/abc123.png"),
     *             @OA\Property(property="message", type="string", example="Logo uploaded successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Archivo inválido"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al subir el archivo"
     *     )
     * )
     */
    public function uploadTenantLogo(UploadTenantLogoRequest $request)
    {
        $validated = $request->validated();

        try {
            $file = $request->file('logo');

            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            // Store in public/tenants/logos
            $path = $file->storeAs('tenants/logos', $filename, 'public');

            // Generate public URL
            $url = Storage::url($path);

            return response()->json([
                'success' => true,
                'url' => $path, // Return path for database storage (accessor will convert to URL)
                'full_url' => $url, // Full URL for preview if needed
                'path' => $path,
                'message' => 'Logo uploaded successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading logo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/upload/file",
     *     tags={"Archivos"},
     *     summary="Eliminar archivo",
     *     description="Elimina un archivo previamente subido",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"path"},
     *             @OA\Property(property="path", type="string", example="tenants/logos/abc123.png", description="Ruta del archivo a eliminar")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Archivo eliminado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="File deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Archivo no encontrado"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al eliminar el archivo"
     *     )
     * )
     */
    public function deleteFile(DeleteFileRequest $request)
    {
        $validated = $request->validated();

        try {
            $path = $validated['path'];

            // Remove /storage/ prefix if present
            $path = str_replace('/storage/', '', $path);

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);

                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting file: ' . $e->getMessage()
            ], 500);
        }
    }
}
