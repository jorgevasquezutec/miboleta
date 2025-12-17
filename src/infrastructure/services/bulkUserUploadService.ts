import apiClient from '@/infrastructure/http/apiClient';
import type {
    UserBatch,
    PaginatedBatchList,
    TemplateConfig,
    BulkUploadConfigData,
    BulkUploadOptions,
} from '@/domain/types/bulkUserUpload.types';

export class BulkUserUploadService {

    // ────────────────────────────────────────────────────────────
    // CONFIGURATION
    // ────────────────────────────────────────────────────────────

    /**
     * Obtener configuración para el modal de template
     */
    async getConfig(): Promise<BulkUploadConfigData> {
        const response = await apiClient.get('/user-batches/config');
        return response.data;
    }

    // ────────────────────────────────────────────────────────────
    // TEMPLATE GENERATION
    // ────────────────────────────────────────────────────────────

    /**
     * Descargar template Excel personalizado
     */
    async downloadTemplate(config: TemplateConfig): Promise<void> {
        const response = await apiClient.post('/user-batches/template', config, {
            responseType: 'blob',
        });

        // Crear download link
        const blob = new Blob([response.data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });

        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;

        // Extraer filename del header o usar default
        const contentDisposition = response.headers['content-disposition'];
        const filename = contentDisposition
            ? contentDisposition.split('filename=')[1]?.replace(/"/g, '')
            : `template_usuarios_${config.max_organizations}orgs_${new Date().getTime()}.xlsx`;

        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    // ────────────────────────────────────────────────────────────
    // BATCH MANAGEMENT
    // ────────────────────────────────────────────────────────────

    /**
     * Listar batches con paginación
     */
    async listBatches(params?: {
        page?: number;
        per_page?: number;
        status?: string;
    }): Promise<PaginatedBatchList> {
        const response = await apiClient.get('/user-batches', { params });
        return response.data;
    }

    /**
     * Obtener detalle de un batch
     */
    async getBatch(id: string | number): Promise<UserBatch> {
        const response = await apiClient.get(`/user-batches/${id}`);
        return response.data;
    }

    /**
     * Validar archivo y obtener preview (sin procesar)
     */
    async validateFile(file: File): Promise<{
        valid: boolean;
        data: any[];
        errors: any[];
        warnings: any[];
        summary: {
            total: number;
            valid: number;
            errors: number;
            warnings: number;
        };
        file_info: {
            name: string;
            size: number;
        };
    }> {
        const formData = new FormData();
        formData.append('file', file);

        const response = await apiClient.post('/user-batches/validate', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        return response.data;
    }

    /**
     * Iniciar carga masiva (datos ya validados)
     */
    async uploadFile(
        file: File,
        options: BulkUploadOptions
    ): Promise<{
        message: string;
        batch: {
            id: number;
            uuid: string;
            total_rows: number;
            status: string;
            status_text: string;
        };
        warnings: any[];
    }> {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('send_welcome_emails', options.send_welcome_emails ? '1' : '0');
        formData.append('update_existing', options.update_existing ? '1' : '0');

        const response = await apiClient.post('/user-batches', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        return response.data;
    }

    /**
     * Eliminar un batch
     */
    async deleteBatch(id: string | number): Promise<void> {
        await apiClient.delete(`/user-batches/${id}`);
    }

    /**
     * Descargar errores de un batch
     */
    async downloadErrors(id: string | number): Promise<void> {
        const response = await apiClient.get(`/user-batches/${id}/errors`, {
            responseType: 'blob',
        });

        const blob = new Blob([response.data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });

        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `errores_carga_${id}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    // ────────────────────────────────────────────────────────────
    // HELPERS
    // ────────────────────────────────────────────────────────────

    /**
     * Formatear tamaño de archivo
     */
    formatFileSize(bytes: number): string {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Formatear duración en segundos
     */
    formatDuration(seconds: number | null): string {
        if (seconds === null) return '-';

        if (seconds < 60) {
            return `${seconds}s`;
        }

        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;

        return `${minutes}m ${secs}s`;
    }
}

export const bulkUserUploadService = new BulkUserUploadService();
