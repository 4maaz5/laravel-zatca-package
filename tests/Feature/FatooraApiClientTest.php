<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Http;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\ApiException;
use Maaz\LaravelZatca\Phase2\Api\FatooraApiClient;
use Maaz\LaravelZatca\Support\ZatcaLogger;
use Maaz\LaravelZatca\Tests\TestCase;

class FatooraApiClientTest extends TestCase
{
    public function test_it_submits_a_clearance_request_using_mocked_http(): void
    {
        Http::fake([
            'https://example.test/invoices/clearance/single' => Http::response([
                'clearanceStatus' => 'CLEARED',
            ], 200),
        ]);

        $client = new FatooraApiClient($this->app->make(\Illuminate\Http\Client\Factory::class), new ZatcaLogger($this->app->make(LogManager::class)));

        $result = $client->submit([
            'invoiceHash' => 'abc123',
            'uuid' => 'uuid-1',
            'invoice' => 'encoded-xml',
        ], $this->tenantConfig(), 'clearance');

        $this->assertTrue($result['success']);
        $this->assertSame('clearance', $result['mode']);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame('CLEARED', $result['body']['clearanceStatus']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://example.test/invoices/clearance/single'
                && $request->hasHeader('Accept-Version', 'V2')
                && $request->hasHeader('accept-language', 'en')
                && $request->hasHeader('Clearance-Status', '1')
                && $request['uuid'] === 'uuid-1';
        });
    }

    public function test_it_submits_a_reporting_request_with_reporting_clearance_status(): void
    {
        Http::fake([
            'https://example.test/invoices/reporting/single' => Http::response([
                'reportingStatus' => 'REPORTED',
            ], 200),
        ]);

        $client = new FatooraApiClient($this->app->make(\Illuminate\Http\Client\Factory::class), new ZatcaLogger($this->app->make(LogManager::class)));

        $result = $client->submit([
            'invoiceHash' => 'abc123',
            'uuid' => 'uuid-2',
            'invoice' => 'encoded-xml',
        ], $this->tenantConfig(), 'reporting');

        $this->assertTrue($result['success']);
        $this->assertSame('reporting', $result['mode']);
        $this->assertSame('REPORTED', $result['body']['reportingStatus']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://example.test/invoices/reporting/single'
                && $request->hasHeader('Accept-Version', 'V2')
                && $request->hasHeader('accept-language', 'en')
                && $request->hasHeader('Clearance-Status', '0')
                && $request['uuid'] === 'uuid-2';
        });
    }

    public function test_it_throws_an_exception_for_server_errors(): void
    {
        Http::fake([
            'https://example.test/invoices/reporting/single' => Http::response([
                'message' => 'server error',
            ], 500),
        ]);

        $client = new FatooraApiClient($this->app->make(\Illuminate\Http\Client\Factory::class), new ZatcaLogger($this->app->make(LogManager::class)));

        $this->expectException(ApiException::class);

        $client->submit([
            'invoiceHash' => 'abc123',
            'uuid' => 'uuid-2',
            'invoice' => 'encoded-xml',
        ], $this->tenantConfig(), 'reporting');
    }

    private function tenantConfig(): TenantConfig
    {
        return TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'sandbox',
            'seller_name' => 'Maaz Store',
            'seller_vat_number' => '300000000000003',
            'api' => [
                'base_url' => 'https://example.test',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'accept_version' => 'V2',
                'accept_language' => 'en',
                'clearance_status' => [
                    'reporting' => '0',
                    'clearance' => '1',
                ],
                'timeout' => 30,
            ],
        ]);
    }
}
