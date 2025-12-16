<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Document;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_has_correct_default_status(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertEquals('active', $tenant->status);
    }

    public function test_tenant_can_be_inactive(): void
    {
        $tenant = Tenant::factory()->inactive()->create();

        $this->assertEquals('inactive', $tenant->status);
    }

    public function test_tenant_can_have_users(): void
    {
        $tenant = Tenant::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $tenant->users()->attach($user->id);
        }

        $this->assertCount(3, $tenant->users);
    }

    public function test_tenant_can_have_documents(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        Document::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(5, $tenant->documents);
    }

    public function test_tenant_has_ruc(): void
    {
        $tenant = Tenant::factory()->create([
            'ruc' => '12345678901',
        ]);

        $this->assertEquals('12345678901', $tenant->ruc);
    }
}
