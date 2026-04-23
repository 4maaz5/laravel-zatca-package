<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Route;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tests\TestCase;

class OnboardingRouteAuthTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('zatca.onboarding.dashboard.require_auth', true);
        $app['config']->set('zatca.onboarding.api.require_auth', true);
        $app['config']->set('zatca.tenant.auth.guests_are_admin', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/login', fn () => 'login')->name('login');
    }

    public function test_dashboard_requires_authentication_when_enabled(): void
    {
        $this->createTenant();

        $this->get('/zatca/onboarding/dashboard')
            ->assertRedirect('/login');
    }

    public function test_api_requires_authentication_when_enabled(): void
    {
        $this->createTenant();

        $this->getJson('/api/zatca/onboarding/tenants')
            ->assertUnauthorized();
    }

    public function test_authenticated_tenant_users_can_access_their_dashboard_when_auth_is_required(): void
    {
        $this->createTenant();

        $this->actingAs($this->tenantUser('bi-tech'));

        $this->get('/zatca/onboarding/dashboard')
            ->assertOk()
            ->assertSee('BI Technology Company');
    }

    private function createTenant(): void
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'bi-tech',
            'legal_name' => 'BI Technology Company',
            'seller_name' => 'BI Technology Company',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        ZatcaTenantCredential::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'status' => 'draft',
        ]);
    }

    private function tenantUser(string $tenantKey): Authenticatable
    {
        return new class ($tenantKey) extends Authenticatable {
            public string $tenant_key;

            public function __construct(string $tenantKey)
            {
                $this->tenant_key = $tenantKey;
            }
        };
    }
}
