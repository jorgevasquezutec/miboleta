<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Support\Facades\Auth;

class UsersTemplateExport implements WithMultipleSheets
{
    private int $maxOrganizations;
    private array $organizations;
    private array $supervisorsByOrg;
    private ?\App\Models\User $actor;

    public function __construct(
        int $maxOrganizations = 3,
        ?array $organizationIds = null
    ) {
        $this->maxOrganizations = min($maxOrganizations, 5); // Max 5 orgs

        // Obtener organizaciones disponibles
        /** @var \App\Models\User $user */
        $user = Auth::user();
        // [OBS-CLIENTE 2026-08]: se propaga a ValidationRulesSheet/
        // InstructionsSheet para que el dropdown/instrucciones de
        // admin_tenant en la plantilla dependan de quién la descarga.
        $this->actor = $user;

        if ($organizationIds) {
            // Filtrar solo las organizaciones seleccionadas
            $this->organizations = \App\Models\Tenant::whereIn('id', $organizationIds)
                ->where('status', 'active')
                ->get()
                ->toArray();
        } else {
            // Todas las organizaciones del usuario
            if ($user->isRoot()) {
                $this->organizations = \App\Models\Tenant::where('status', 'active')
                    ->get()
                    ->toArray();
            } else {
                $this->organizations = $user->tenants->toArray();
            }
        }

        // Obtener supervisores por organización
        $this->supervisorsByOrg = $this->getSupervisorsByOrganization();
    }

    /**
     * Obtener supervisores agrupados por organización
     */
    private function getSupervisorsByOrganization(): array
    {
        $result = [];

        foreach ($this->organizations as $org) {
            $supervisors = \App\Models\User::whereHas('tenants', function ($q) use ($org) {
                $q->where('tenants.id', $org['id']);
            })
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'admin');
                })
                ->select('id', 'email', 'name', 'last_name')
                ->get();

            $result[$org['id']] = $supervisors->map(fn($s) => [
                'id' => $s->id,
                'email' => $s->email,
                'full_name' => $s->full_name ?? ($s->name . ' ' . $s->last_name),
            ])->toArray();
        }

        return $result;
    }

    /**
     * Retornar array de sheets para el Excel
     */
    public function sheets(): array
    {
        return [
            // Hoja 1: Template principal con datos de usuarios
            new Sheets\UsersSheetTemplate(
                $this->maxOrganizations,
                $this->organizations
            ),

            // Hoja 2: Catálogo de organizaciones
            new Sheets\OrganizationsCatalogSheet(
                $this->organizations
            ),

            // Hoja 3: Catálogo de supervisores
            new Sheets\SupervisorsCatalogSheet(
                $this->supervisorsByOrg,
                $this->organizations
            ),

            // Hoja 4: Validaciones (listas desplegables - oculta)
            new Sheets\ValidationRulesSheet(
                $this->organizations,
                $this->supervisorsByOrg,
                $this->maxOrganizations,
                $this->actor
            ),

            // Hoja 5: Instrucciones de uso
            new Sheets\InstructionsSheet(
                $this->maxOrganizations,
                $this->actor
            ),
        ];
    }
}
