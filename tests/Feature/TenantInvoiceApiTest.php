<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Maaz\LaravelZatca\Tenancy\Invoices\TenantInvoiceSubmissionFlow;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoice;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoiceState;
use Maaz\LaravelZatca\Services\ZatcaManager;
use Maaz\LaravelZatca\Tests\TestCase;

class TenantInvoiceApiTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_lists_tenant_invoices(): void
    {
        $tenant = $this->createTenant();

        ZatcaTenantInvoice::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'invoice_number' => 'INV-1001',
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'mode' => 'reporting',
            'status' => 'submitted',
            'reporting_status' => 'REPORTED',
            'invoice_hash' => 'hash-1',
            'qr_code' => 'qr-1',
            'seller' => ['name' => 'BI Technology Company'],
            'items' => [['name' => 'Subscription', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 15]],
            'xml' => '<Invoice>raw</Invoice>',
            'signed_xml' => '<Invoice>signed</Invoice>',
            'api_response' => ['status' => 'ok'],
        ]);

        $response = $this->getJson('/api/zatca/onboarding/tenants/bi-tech/invoices');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'INV-1001')
            ->assertJsonPath('data.0.reporting_status', 'REPORTED');
    }

    public function test_it_filters_and_paginates_tenant_invoices(): void
    {
        $tenant = $this->createTenant();

        ZatcaTenantInvoice::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'invoice_number' => 'RPT-1001',
            'uuid' => 'aaaa1111-1111-1111-1111-111111111111',
            'mode' => 'reporting',
            'status' => 'submitted',
            'reporting_status' => 'REPORTED',
            'invoice_hash' => 'hash-a',
            'qr_code' => 'qr-a',
            'seller' => ['name' => 'BI Technology Company'],
            'items' => [['name' => 'Subscription', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 15]],
            'submitted_at' => now()->subDays(2),
        ]);

        ZatcaTenantInvoice::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'invoice_number' => 'CLR-1002',
            'uuid' => 'bbbb2222-2222-2222-2222-222222222222',
            'mode' => 'clearance',
            'status' => 'failed',
            'clearance_status' => 'NOT_CLEARED',
            'invoice_hash' => 'hash-b',
            'qr_code' => 'qr-b',
            'seller' => ['name' => 'BI Technology Company'],
            'items' => [['name' => 'Subscription', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 15]],
            'submitted_at' => now(),
        ]);

        $response = $this->getJson('/api/zatca/onboarding/tenants/bi-tech/invoices?mode=clearance&status=failed&date_from=' . now()->subDay()->toDateString() . '&per_page=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'CLR-1002')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_shows_invoice_details_and_downloads_saved_artifacts(): void
    {
        $tenant = $this->createTenant();

        $invoice = ZatcaTenantInvoice::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'invoice_number' => 'INV-3001',
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'mode' => 'reporting',
            'status' => 'submitted',
            'reporting_status' => 'REPORTED',
            'invoice_hash' => 'hash-3',
            'qr_code' => 'qr-3',
            'seller' => ['name' => 'BI Technology Company'],
            'items' => [['name' => 'Plan', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 15]],
            'xml' => '<Invoice>raw</Invoice>',
            'signed_xml' => '<Invoice>signed</Invoice>',
            'api_response' => ['reportingStatus' => 'REPORTED'],
        ]);

        $detail = $this->getJson('/api/zatca/onboarding/tenants/bi-tech/invoices/' . $invoice->getKey());

        $detail->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-3001')
            ->assertJsonPath('data.xml', '<Invoice>raw</Invoice>')
            ->assertJsonPath('data.signed_xml', '<Invoice>signed</Invoice>')
            ->assertJsonPath('data.download_urls.xml', '/api/zatca/onboarding/tenants/bi-tech/invoices/' . $invoice->getKey() . '/xml');

        $this->get('/api/zatca/onboarding/tenants/bi-tech/invoices/' . $invoice->getKey() . '/xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('<Invoice>raw</Invoice>', false);

        $this->get('/api/zatca/onboarding/tenants/bi-tech/invoices/' . $invoice->getKey() . '/signed-xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('<Invoice>signed</Invoice>', false);

        $this->get('/api/zatca/onboarding/tenants/bi-tech/invoices/' . $invoice->getKey() . '/api-response')
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8')
            ->assertSee('REPORTED');
    }

    public function test_it_submits_an_invoice_and_returns_updated_history(): void
    {
        $tenant = $this->createTenant();

        $invoice = ZatcaTenantInvoice::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'invoice_number' => 'INV-2001',
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'mode' => 'clearance',
            'status' => 'submitted',
            'clearance_status' => 'CLEARED',
            'invoice_hash' => 'hash-2',
            'qr_code' => 'qr-2',
            'seller' => ['name' => 'BI Technology Company'],
            'buyer' => ['name' => 'Customer A'],
            'items' => [['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 15]],
        ]);

        $flow = $this->mock(TenantInvoiceSubmissionFlow::class);
        $flow->shouldReceive('submitInvoice')
            ->once()
            ->andReturn($invoice);
        $flow->shouldReceive('listInvoices')
            ->once()
            ->andReturn(new Collection([$invoice]));

        $this->app->instance(TenantInvoiceSubmissionFlow::class, $flow);

        $response = $this->postJson('/api/zatca/onboarding/tenants/bi-tech/invoices', [
            'environment' => 'sandbox',
            'mode' => 'clearance',
            'invoice_number' => 'INV-2001',
            'items' => [
                [
                    'name' => 'Service',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_percent' => 15,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Invoice submitted successfully.')
            ->assertJsonPath('invoice.invoice_number', 'INV-2001')
            ->assertJsonPath('invoice.clearance_status', 'CLEARED')
            ->assertJsonCount(1, 'invoices')
            ->assertJsonPath('tenant.key', 'bi-tech');
    }

    public function test_it_returns_a_friendly_message_when_invoice_credentials_are_not_ready(): void
    {
        $this->createTenant();

        $response = $this->postJson('/api/zatca/onboarding/tenants/bi-tech/invoices', [
            'environment' => 'sandbox',
            'mode' => 'reporting',
            'invoice_number' => 'INV-READY-1001',
            'items' => [
                [
                    'name' => 'Subscription',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_percent' => 15,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'ApiException')
            ->assertJsonPath(
                'message',
                'The sandbox invoice credential is missing its private key. Generate the CSR again or complete onboarding before submitting invoices.'
            );
    }

    public function test_it_normalizes_tenant_invoice_type_and_transaction_type_codes(): void
    {
        $flow = new class ($this->app->make(ZatcaManager::class)) extends TenantInvoiceSubmissionFlow {
            public function publicNormalizeInvoiceTypeCode(mixed $type): string
            {
                return $this->normalizeInvoiceTypeCode($type);
            }

            public function publicNormalizeInvoiceMeta(mixed $meta, string $mode): array
            {
                return $this->normalizeInvoiceMeta($meta, $mode);
            }
        };

        $this->assertSame('388', $flow->publicNormalizeInvoiceTypeCode('Report'));
        $this->assertSame('388', $flow->publicNormalizeInvoiceTypeCode('standard invoice'));
        $this->assertSame('381', $flow->publicNormalizeInvoiceTypeCode('credit note'));
        $this->assertSame('383', $flow->publicNormalizeInvoiceTypeCode('debit-note'));
        $this->assertSame('388', $flow->publicNormalizeInvoiceTypeCode('388'));

        $this->assertSame('0200000', $flow->publicNormalizeInvoiceMeta([], 'reporting')['transaction_type_code']);
        $this->assertSame('0100000', $flow->publicNormalizeInvoiceMeta([], 'clearance')['transaction_type_code']);
        $this->assertSame('0201000', $flow->publicNormalizeInvoiceMeta(['transaction_type_code' => '0201000'], 'reporting')['transaction_type_code']);
    }

    public function test_tenant_users_cannot_access_other_tenant_invoice_endpoints(): void
    {
        $this->createTenant();

        $otherTenant = ZatcaTenant::query()->create([
            'key' => 'other-tenant',
            'legal_name' => 'Other Company',
            'seller_name' => 'Other Company',
            'vat_number' => '300000000000013',
            'default_environment' => 'sandbox',
            'locale' => 'en',
        ]);

        foreach (['sandbox', 'production'] as $environment) {
            ZatcaTenantCredential::query()->create([
                'tenant_id' => $otherTenant->getKey(),
                'environment' => $environment,
                'status' => 'draft',
            ]);

            ZatcaTenantInvoiceState::query()->create([
                'tenant_id' => $otherTenant->getKey(),
                'environment' => $environment,
                'last_icv' => 0,
            ]);
        }

        ZatcaTenantInvoice::query()->create([
            'tenant_id' => $otherTenant->getKey(),
            'environment' => 'sandbox',
            'invoice_number' => 'INV-9999',
            'uuid' => '99999999-9999-9999-9999-999999999999',
            'mode' => 'reporting',
            'status' => 'submitted',
            'reporting_status' => 'REPORTED',
            'invoice_hash' => 'hash-9',
            'qr_code' => 'qr-9',
            'seller' => ['name' => 'Other Company'],
            'items' => [['name' => 'Support', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 15]],
        ]);

        $this->actingAs($this->tenantUser('bi-tech'));

        $this->getJson('/api/zatca/onboarding/tenants/other-tenant/invoices')
            ->assertForbidden();
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

        return $tenant;
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

