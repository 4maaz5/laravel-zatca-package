<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Maaz\LaravelZatca\Concerns\InteractsWithZatcaTenantWorkspace;
use Maaz\LaravelZatca\Contracts\TenantAwareUser;
use Maaz\LaravelZatca\Tests\TestCase;

class TenantWorkspaceUserTraitTest extends TestCase
{
    public function test_it_exposes_tenant_key_and_admin_status_for_host_app_users(): void
    {
        $user = new class () extends Authenticatable implements TenantAwareUser {
            use InteractsWithZatcaTenantWorkspace;

            public string $tenant_key = 'bi-tech';

            public bool $is_super_admin = true;
        };

        $this->assertSame('bi-tech', $user->zatcaTenantKey());
        $this->assertTrue($user->zatcaCanManageTenants());
    }
}
