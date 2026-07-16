<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

class ReprocessChunkRequest extends FormRequest
{
    use ResolvesActiveRole;

    public function authorize(): bool
    {
        // Previsualizar un ZIP es parte del flujo de carga masiva de documentos,
        // así que va con la misma ability que UploadZipBatchRequest
        // (matriz: 'documents.bulk_upload_zip' = admin, admin_tenant).
        // Cambia respecto de ['root','admin']: root no carga ZIPs y
        // admin_tenant sí.
        return $this->allowsAbility('documents.bulk_upload_zip');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:zip|max:102400',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo es requerido',
            'file.file' => 'Debe ser un archivo válido',
            'file.mimes' => 'El archivo debe ser un ZIP',
            'file.max' => 'El archivo no puede exceder 100MB',
        ];
    }
}
