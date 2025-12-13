<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteFileRequest;
use App\Http\Requests\UploadTenantLogoRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    /**
     * Upload tenant logo
     *
     * @param UploadTenantLogoRequest $request
     * @return \Illuminate\Http\JsonResponse
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
     * Delete uploaded file
     *
     * @param DeleteFileRequest $request
     * @return \Illuminate\Http\JsonResponse
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
