<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Http;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Phase2\Api\FatooraOnboardingClient;
use Maaz\LaravelZatca\Support\ZatcaLogger;
use Maaz\LaravelZatca\Tests\TestCase;

class FatooraOnboardingClientTest extends TestCase
{
    public function test_it_requests_compliance_csid_using_default_sandbox_endpoint(): void
    {
        Http::fake([
            'https://example.test/compliance' => Http::response([
                'requestID' => 1234567890123,
                'binarySecurityToken' => 'token',
                'secret' => 'secret',
            ], 200),
        ]);

        $client = $this->client();

        $result = $client->requestComplianceCsid([
            'otp' => '123345',
            'csr' => 'base64-csr',
        ], $this->tenantConfig());

        $this->assertTrue($result['success']);
        $this->assertSame('compliance_csid', $result['stage']);
        $this->assertSame(1234567890123, $result['body']['requestID']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://example.test/compliance'
                && $request->hasHeader('Accept-Version', 'V2')
                && $request->hasHeader('OTP', '123345')
                && $request['csr'] === 'base64-csr';
        });
    }

    public function test_it_sends_accept_language_for_compliance_invoice_checks(): void
    {
        Http::fake([
            'https://example.test/compliance/invoices' => Http::response([
                'status' => 'PASS',
            ], 200),
        ]);

        $client = $this->client();

        $result = $client->complianceCheck([
            'uuid' => 'uuid-1',
            'invoiceHash' => 'hash',
            'invoice' => 'encoded-xml',
        ], $this->tenantConfig());

        $this->assertTrue($result['success']);
        $this->assertSame('compliance_checks', $result['stage']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://example.test/compliance/invoices'
                && $request->hasHeader('Accept-Version', 'V2')
                && $request->hasHeader('Accept-Language', 'en')
                && $request['uuid'] === 'uuid-1'
                && $request['invoiceHash'] === 'hash';
        });
    }

    private function client(): FatooraOnboardingClient
    {
        return new FatooraOnboardingClient(
            $this->app->make(\Illuminate\Http\Client\Factory::class),
            new ZatcaLogger($this->app->make(LogManager::class))
        );
    }

    private function tenantConfig(): TenantConfig
    {
        return TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'sandbox',
            'seller_name' => 'Maaz Store',
            'seller_vat_number' => '300000000000003',
            'language' => 'en',
            'api' => [
                'base_url' => 'https://example.test',
                'binary_security_token' => 'token',
                'secret' => 'secret',
                'accept_version' => 'V2',
                'timeout' => 30,
            ],
        ]);
    }
}
