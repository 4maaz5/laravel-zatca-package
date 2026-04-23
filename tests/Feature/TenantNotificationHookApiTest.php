<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Maaz\LaravelZatca\Events\TenantCredentialHealthAlertDetected;
use Maaz\LaravelZatca\Events\TenantInvoiceSubmissionAlertDetected;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoice;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoiceState;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantNotificationHook;
use Maaz\LaravelZatca\Tests\TestCase;

class TenantNotificationHookApiTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_manages_notification_hooks_for_a_tenant(): void
    {
        $tenant = $this->createTenant();

        $store = $this->postJson('/api/zatca/onboarding/tenants/bi-tech/notification-hooks', [
            'name' => 'Ops Webhook',
            'target_url' => 'https://example.test/hooks/zatca',
            'events' => ['health_alert', 'submission_failed'],
            'is_active' => true,
        ]);

        $hookId = $store->json('hook.id');

        $store->assertOk()
            ->assertJsonPath('hook.name', 'Ops Webhook')
            ->assertJsonPath('tenant.notification_hooks.0.target_url', 'https://example.test/hooks/zatca');

        $this->patchJson('/api/zatca/onboarding/tenants/bi-tech/notification-hooks/' . $hookId, [
            'name' => 'Primary Ops Webhook',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('hook.name', 'Primary Ops Webhook')
            ->assertJsonPath('hook.is_active', false);

        $this->getJson('/api/zatca/onboarding/tenants/bi-tech/notification-hooks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.events.1', 'submission_failed');

        $this->deleteJson('/api/zatca/onboarding/tenants/bi-tech/notification-hooks/' . $hookId)
            ->assertOk()
            ->assertJsonCount(0, 'tenant.notification_hooks');
    }

    public function test_it_dispatches_health_and_submission_failure_alerts_to_active_hooks(): void
    {
        Http::fake([
            'https://example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $tenant = $this->createTenant();
        $credential = $tenant->credentials()->where('environment', 'sandbox')->firstOrFail();

        $hook = ZatcaTenantNotificationHook::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ops Webhook',
            'channel' => 'webhook',
            'target_url' => 'https://example.test/hooks/zatca',
            'events' => ['health_alert', 'submission_failed'],
            'is_active' => true,
            'secret' => 'shared-secret',
        ]);

        $invoice = ZatcaTenantInvoice::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'invoice_number' => 'INV-9001',
            'uuid' => '99999999-9999-9999-9999-999999999999',
            'mode' => 'reporting',
            'status' => 'failed',
            'invoice_hash' => 'hash-9',
            'qr_code' => 'qr-9',
            'seller' => ['name' => 'BI Technology Company'],
            'items' => [['name' => 'Subscription', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 15]],
            'last_error' => 'Gateway rejected invoice',
        ]);

        Event::dispatch(new TenantCredentialHealthAlertDetected($tenant, $credential, [
            'environment' => 'sandbox',
            'status' => 'warning',
            'labels' => ['en' => 'Warning', 'ar' => 'تحذير'],
            'issues' => [
                [
                    'code' => 'certificate_expiring_soon',
                    'severity' => 'warning',
                    'message' => ['en' => 'Certificate expires soon.', 'ar' => 'ستنتهي الشهادة قريباً.'],
                ],
            ],
        ]));

        Event::dispatch(new TenantInvoiceSubmissionAlertDetected($tenant, $invoice));

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->hasHeader('X-Zatca-Hook-Secret', 'shared-secret')
                && in_array($data['event'] ?? null, ['health_alert', 'submission_failed'], true);
        });

        $hook->refresh();

        $this->assertNotNull($hook->last_notified_at);
        $this->assertNull($hook->last_error);
    }

    private function createTenant(): ZatcaTenant
    {
        $tenant = ZatcaTenant::query()->create([
            'key' => 'bi-tech',
            'legal_name' => 'BI Technology Company',
            'seller_name' => 'BI Technology Company',
            'vat_number' => '313138851500003',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        foreach (['sandbox', 'production'] as $environment) {
            ZatcaTenantCredential::query()->create([
                'tenant_id' => $tenant->getKey(),
                'environment' => $environment,
                'status' => 'draft',
            ]);

            ZatcaTenantInvoiceState::query()->create([
                'tenant_id' => $tenant->getKey(),
                'environment' => $environment,
                'last_icv' => 0,
            ]);
        }

        return $tenant->fresh(['credentials']);
    }
}
