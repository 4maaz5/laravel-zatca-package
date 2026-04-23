<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Log\LogManager;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\Phase2\Builders\UblInvoiceBuilder;
use Maaz\LaravelZatca\Phase2\Hashing\ZatcaInvoiceHashGenerator;
use Maaz\LaravelZatca\Phase2\Qr\Phase2QrCodeService;
use Maaz\LaravelZatca\Phase2\Signatures\SignatureService;
use Maaz\LaravelZatca\Services\SubmissionPipeline;
use Maaz\LaravelZatca\Support\CertificateLoader;
use Maaz\LaravelZatca\Support\ZatcaLogger;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoiceState;
use Maaz\LaravelZatca\Tenancy\Stores\DatabaseTenantInvoiceStateStore;
use Maaz\LaravelZatca\Tests\TestCase;

class TenantInvoiceStatePersistenceTest extends TestCase
{
    private const VALID_PIH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_updates_tenant_invoice_state_after_successful_submission(): void
    {
        [$privateKey, $certificate] = $this->sdkCertificatePair();

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
            'status' => 'production_issued',
            'private_key' => $privateKey,
            'production_binary_security_token' => $certificate,
            'production_secret' => 'secret',
        ]);

        $state = ZatcaTenantInvoiceState::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => 'sandbox',
            'last_icv' => 41,
            'previous_invoice_hash' => 'old-hash',
        ]);

        $tenantConfig = TenantConfig::fromArray([
            'tenant_id' => (string) $tenant->getKey(),
            'environment' => 'sandbox',
            'seller_name' => 'BI Technology Company',
            'seller_vat_number' => '313138851500003',
            'certificates' => [
                'private_key' => $privateKey,
                'certificate' => $certificate,
            ],
            'api' => [],
            'meta' => [
                'icv' => '42',
                'pih' => 'old-hash',
            ],
        ]);

        $invoice = Zatca::invoice()
            ->invoiceNumber('INV-STATE-1')
            ->issuedAt('2026-04-13T10:30:00+03:00')
            ->seller([
                'name' => 'BI Technology Company',
                'vat_number' => '313138851500003',
                'crn' => '7050816433',
                'street' => 'Saidya',
                'building_number' => '7036',
                'additional_number' => '7036',
                'district' => 'AL Duraihemiyah',
                'city' => 'Riyadh',
                'postal_code' => '12796',
            ])
            ->addItem([
                'name' => 'Subscription',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_percent' => 15,
            ])
            ->meta([
                'icv' => '42',
                'pih' => self::VALID_PIH,
            ])
            ->generate();

        $pipeline = new SubmissionPipeline(
            new UblInvoiceBuilder(),
            new SignatureService(new CertificateLoader(), new ZatcaInvoiceHashGenerator()),
            new Phase2QrCodeService(new \Maaz\LaravelZatca\Phase1\Encoders\TlvEncoder(), new CertificateLoader(), new ZatcaInvoiceHashGenerator()),
            new class implements \Maaz\LaravelZatca\Contracts\ApiClient {
                public function submit(array $payload, TenantConfig $tenantConfig, string $mode): array
                {
                    return [
                        'success' => true,
                        'status_code' => 200,
                        'body' => ['reportingStatus' => 'REPORTED'],
                    ];
                }
            },
            new ZatcaInvoiceHashGenerator(),
            new ZatcaLogger($this->app->make(LogManager::class)),
            new CertificateLoader(),
            new DatabaseTenantInvoiceStateStore()
        );

        $result = $pipeline->submit($invoice, $tenantConfig, 'reporting');

        $state->refresh();

        $this->assertTrue($result->accepted());
        $this->assertSame(42, $state->last_icv);
        $this->assertSame($result->invoiceHash, $state->previous_invoice_hash);
        $this->assertSame($invoice->uuid, $state->last_invoice_uuid);
        $this->assertSame($result->invoiceHash, $state->last_invoice_hash);
        $this->assertNotNull($state->last_submitted_at);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sdkCertificatePair(): array
    {
        $root = dirname(__DIR__, 2) . '/.net sdk/zatca-einvoicing-sdk-DotNet-238-R3.4.8/Data/Certificates';
        $certificatePath = $root . '/cert.pem';
        $privateKeyPath = $root . '/ec-secp256k1-priv-key.pem';

        if (! is_file($certificatePath) || ! is_file($privateKeyPath)) {
            $this->markTestSkipped('Official SDK certificate fixtures are not available.');
        }

        $privateKey = file_get_contents($privateKeyPath);
        $certificate = file_get_contents($certificatePath);

        $this->assertIsString($privateKey);
        $this->assertIsString($certificate);

        return [$privateKey, $certificate];
    }
}
