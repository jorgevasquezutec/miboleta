<?php

namespace Tests\Unit\Services;

use App\Services\BulkUserUploadService;
use Tests\TestCase;

/**
 * Obs 2: la carga masiva y sus reportes mostraban el slug crudo del rol
 * ("client") en vez del display name que ya vive en roles.display_name
 * ("Empleado"). orgRoleLabel()/orgRoleSlug() son la fuente única para
 * traducir en ambas direcciones (ver su uso en UsersImport, ValidationRulesSheet,
 * InstructionsSheet, UsersSheetTemplate, ReportsService y
 * UploadUserBatchDataRequest).
 */
class BulkUserUploadServiceTest extends TestCase
{
    public function test_org_role_label_maps_known_slugs_to_display_names(): void
    {
        $this->assertSame('Empleado', BulkUserUploadService::orgRoleLabel('client'));
        $this->assertSame('Admin Empleados', BulkUserUploadService::orgRoleLabel('admin'));
        $this->assertSame('Aprobador Empleado', BulkUserUploadService::orgRoleLabel('aprobador'));
        $this->assertSame('Admin Clientes', BulkUserUploadService::orgRoleLabel('admin_tenant'));
    }

    public function test_org_role_label_falls_back_to_slug_when_unknown(): void
    {
        $this->assertSame('root', BulkUserUploadService::orgRoleLabel('root'));
        $this->assertSame('inexistente', BulkUserUploadService::orgRoleLabel('inexistente'));
    }

    public function test_org_role_slug_accepts_a_lowercase_slug(): void
    {
        $this->assertSame('client', BulkUserUploadService::orgRoleSlug('client'));
    }

    public function test_org_role_slug_accepts_a_display_name(): void
    {
        $this->assertSame('client', BulkUserUploadService::orgRoleSlug('Empleado'));
    }

    public function test_org_role_slug_is_case_and_whitespace_insensitive(): void
    {
        $this->assertSame('client', BulkUserUploadService::orgRoleSlug('EMPLEADO '));
        $this->assertSame('client', BulkUserUploadService::orgRoleSlug(' client '));
    }

    public function test_org_role_slug_accepts_admin_tenant_display_name(): void
    {
        $this->assertSame('admin_tenant', BulkUserUploadService::orgRoleSlug('Admin Clientes'));
    }

    public function test_org_role_slug_returns_null_for_unknown_value(): void
    {
        $this->assertNull(BulkUserUploadService::orgRoleSlug('gerente'));
        $this->assertNull(BulkUserUploadService::orgRoleSlug(''));
    }
}
