<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * [Área 2] Corrige tenants.initial_employee_count, el snapshot congelado que
 * UserBatch::syncInitialEmployeeCounts fija UNA sola vez al completar la
 * primera carga masiva de cada empresa. Ese snapshot histórico se calculó
 * con la fórmula VIEJA de Tenant::employeesQuery() (previa a [Área 1]), que
 * contaba a TODOS los miembros de user_tenants sin filtrar por rol -
 * incluyendo a los admin_tenant "puros" (cuentas de aplicación: administran
 * la empresa, no son empleados)-. La fórmula NUEVA los excluye (ver
 * User::ORG_EMPLOYEE_ROLES / Tenant::employeesQuery()), así que sin esta
 * migración el snapshot histórico queda inflado respecto al criterio actual
 * y Tenant::employeeCounts()['subsequent_employee_count'] sale
 * artificialmente bajo (o truncado en 0 cuando debería ser mayor).
 *
 * NO re-ejecutar manualmente: una segunda corrida restaría los admin_tenant
 * puros DOS veces (tras la primera corrección, ese "exceso" ya no está en
 * el snapshot). La idempotencia la garantiza el ledger de migraciones de
 * Laravel (una fila en la tabla `migrations`), no la lógica de este
 * archivo.
 *
 * Roles referenciados por nombre (`name`) en la tabla `roles`, no por clase
 * de modelo -sigue el patrón de 2026_07_17_000001_relabel_roles_display_names
 * y 2026_07_31_000001_fix_user_batches_tenant_id, que evitan Eloquent en
 * migraciones de datos-. El array ORG_EMPLOYEE_ROLES de abajo es un espejo
 * literal de User::ORG_EMPLOYEE_ROLES (app/Models/User.php) al momento de
 * escribir esta migración; si esa constante cambia más adelante, NO hace
 * falta tocar este archivo (ya se aplicó y no se re-ejecuta).
 */
return new class extends Migration
{
    /** Espejo de User::ORG_EMPLOYEE_ROLES (app/Models/User.php). */
    private const ORG_EMPLOYEE_ROLES = ['admin', 'client', 'aprobador'];

    private const ADMIN_TENANT_ROLE = 'admin_tenant';

    public function up(): void
    {
        $this->logMembersWithoutTenantRole();
        $this->recalculateInitialEmployeeCounts();
    }

    /**
     * Diagnóstico (no bloquea la migración): miembros de user_tenants sin
     * ninguna fila en user_tenant_roles para esa misma empresa. Con la
     * fórmula nueva estos usuarios nunca cuentan como empleado -ni con la
     * fórmula vieja ni con la nueva tenían rol-, así que no hay nada que
     * recalcular para ellos; solo se documentan (release notes) porque son
     * quienes más notarán el cambio de fórmula si alguna vez se les asigna
     * un rol después.
     */
    private function logMembersWithoutTenantRole(): void
    {
        $orphans = DB::table('user_tenants as ut')
            ->leftJoin('user_tenant_roles as utr', function ($join) {
                $join->on('utr.user_id', '=', 'ut.user_id')
                    ->on('utr.tenant_id', '=', 'ut.tenant_id');
            })
            ->whereNull('utr.id')
            ->orderBy('ut.tenant_id')
            ->orderBy('ut.user_id')
            ->select('ut.tenant_id', 'ut.user_id')
            ->get();

        if ($orphans->isEmpty()) {
            return;
        }

        Log::warning('[migration] recalculate_initial_employee_count_without_app_accounts: miembros de user_tenants sin rol en user_tenant_roles (no cuentan como empleado con la fórmula nueva)', [
            'count' => $orphans->count(),
            'pairs' => $orphans->map(fn ($o) => ['tenant_id' => $o->tenant_id, 'user_id' => $o->user_id])->all(),
        ]);
    }

    /**
     * Por cada tenant YA fijado (initial_employee_count > 0), resta del
     * snapshot TODOS los miembros (user_tenants) con rol admin_tenant en esa
     * empresa (user_tenant_roles) — incluidos los duales admin_tenant+client:
     * con la regla "admin_tenant domina" (ver Tenant::employeesQuery()), quien
     * es admin_tenant de una empresa es cuenta de aplicación ahí aunque tenga
     * también un rol de empleado, así que la fórmula nueva tampoco lo cuenta.
     *
     * Tenants con initial_employee_count 0/null se saltan a propósito: para
     * ellos el snapshot sigue "sin fijar" (ver guard `!empty` en
     * UserBatch::syncInitialEmployeeCounts) y su primer batch los fijará ya
     * con la fórmula nueva -no hay nada viejo que corregir-.
     */
    private function recalculateInitialEmployeeCounts(): void
    {
        $adminTenantRoleId = DB::table('roles')->where('name', self::ADMIN_TENANT_ROLE)->value('id');

        if (!$adminTenantRoleId) {
            Log::warning('[migration] recalculate_initial_employee_count_without_app_accounts: no existe el rol admin_tenant, no se recalcula nada');

            return;
        }

        $changed = 0;

        DB::table('tenants')
            ->where('initial_employee_count', '>', 0)
            ->orderBy('id')
            ->select('id', 'initial_employee_count')
            ->get()
            ->each(function ($tenant) use ($adminTenantRoleId, &$changed) {
                $adminTenantCount = DB::table('user_tenant_roles as utr')
                    ->join('user_tenants as ut', function ($join) use ($tenant) {
                        $join->on('ut.user_id', '=', 'utr.user_id')
                            ->where('ut.tenant_id', '=', $tenant->id);
                    })
                    ->where('utr.tenant_id', $tenant->id)
                    ->where('utr.role_id', $adminTenantRoleId)
                    ->distinct('utr.user_id')
                    ->count('utr.user_id');

                if ($adminTenantCount === 0) {
                    return; // nada que restar: snapshot ya correcto
                }

                $old = (int) $tenant->initial_employee_count;
                $new = max(0, $old - $adminTenantCount);

                if ($new === $old) {
                    return;
                }

                DB::table('tenants')->where('id', $tenant->id)->update([
                    'initial_employee_count' => $new,
                    'updated_at' => now(),
                ]);

                Log::info('[migration] recalculate_initial_employee_count_without_app_accounts: initial_employee_count corregido', [
                    'tenant_id' => $tenant->id,
                    'old' => $old,
                    'new' => $new,
                ]);

                if ($new === 0) {
                    // Snapshot reabierto a propósito: el guard `!empty` de
                    // UserBatch::syncInitialEmployeeCounts lo tratará como
                    // "sin fijar" y el próximo batch lo fijará con la
                    // fórmula nueva. Comportamiento deseado, no un bug.
                    Log::warning('[migration] recalculate_initial_employee_count_without_app_accounts: snapshot reabierto (quedó en 0)', [
                        'tenant_id' => $tenant->id,
                    ]);
                }

                $changed++;
            });

        Log::info('[migration] recalculate_initial_employee_count_without_app_accounts ejecutado', ['tenants_corregidos' => $changed]);
    }

    /**
     * No-op intencional: el valor previo de initial_employee_count no se
     * guarda antes de corregirlo (no hay snapshot del snapshot), así que no
     * hay forma segura de revertirlo. El valor corregido es, en cualquier
     * caso, el dato correcto según la fórmula vigente -no es una migración
     * de esquema reversible-.
     */
    public function down(): void
    {
        // Intencionalmente vacío. Ver docblock de la clase.
    }
};
