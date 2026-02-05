import apiClient from '../http/apiClient';
import { getCsrfToken } from '@/shared/utils';

export interface UploadResponse {
    success: boolean;
    url?: string;
    path?: string;
    message: string;
}

/**
 * Upload a tenant logo to the server
 * @param file - The image file to upload
 * @returns Promise with the upload response containing the URL
 */
export async function uploadTenantLogo(file: File): Promise<string> {
    const formData = new FormData();
    formData.append('logo', file);

    // Get CSRF token from cookies
    const csrfToken = getCsrfToken();

    try {
        const response = await apiClient.post<UploadResponse>(
            '/upload/tenant-logo',
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    ...(csrfToken && { 'X-XSRF-TOKEN': csrfToken }),
                },
            }
        );

        if (response.data.success && response.data.url) {
            return response.data.url;
        } else {
            throw new Error(response.data.message || 'Upload failed');
        }
    } catch (error: any) {
        const errorMessage = error.response?.data?.message || error.message || 'Error uploading file';
        throw new Error(errorMessage);
    }
}

/**
 * Delete an uploaded file from the server
 * @param path - The file path to delete (can be full URL or path)
 * @returns Promise with success status
 */
export async function deleteUploadedFile(path: string): Promise<boolean> {
    try {
        const response = await apiClient.delete<UploadResponse>('/upload/file', {
            data: { path },
        });

        return response.data.success;
    } catch (error: any) {
        console.error('Error deleting file:', error);
        return false;
    }
}

/**
 * Validate file before upload
 * @param file - The file to validate
 * @param maxSizeMB - Maximum file size in MB (default: 2)
 * @returns Error message if invalid, null if valid
 */
export function validateImageFile(file: File, maxSizeMB: number = 2): string | null {
    // Check file type
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        return 'Por favor selecciona una imagen válida (JPG, PNG, GIF, WEBP)';
    }

    // Check file size
    const maxSizeBytes = maxSizeMB * 1024 * 1024;
    if (file.size > maxSizeBytes) {
        return `El archivo es muy grande. Tamaño máximo: ${maxSizeMB}MB`;
    }

    return null;
}
