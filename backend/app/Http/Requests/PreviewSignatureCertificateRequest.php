<?php

namespace App\Http\Requests;

/**
 * Vista previa (sin guardar) de un certificado de firma: mismas reglas de
 * autorización (root, 'platform.manage') y de archivo/contraseña que la carga
 * por empresa, más el RUC opcional contra el que se compara.
 */
class PreviewSignatureCertificateRequest extends StoreTenantSignatureCertificateRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'ruc' => ['nullable', 'string', 'max:20'],
        ];
    }
}
