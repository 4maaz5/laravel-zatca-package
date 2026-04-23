<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\DTOs\PreparedInvoiceResult;
use Maaz\LaravelZatca\Services\ZatcaManager;
use Maaz\LaravelZatca\Tests\TestCase;

class ComplianceCheckSampleCommandTest extends TestCase
{
    public function test_it_submits_a_sample_compliance_invoice_using_saved_credentials(): void
    {
        $workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-compliance-' . uniqid('', true);
        mkdir($workspace, 0777, true);

        $responsePath = $workspace . DIRECTORY_SEPARATOR . 'compliance-csid.json';
        $privateKeyPath = $workspace . DIRECTORY_SEPARATOR . 'private-key.pem';

        file_put_contents($responsePath, json_encode([
            'body' => [
                'binarySecurityToken' => 'compliance-token',
                'secret' => 'compliance-secret',
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($privateKeyPath, 'private-key');

        $manager = $this->mock(ZatcaManager::class);
        $manager->shouldReceive('invoice->invoiceNumber->issuedAt->seller->buyer->addItem->meta->generate')
            ->once()
            ->andReturn($this->sampleInvoice());
        $manager->shouldReceive('prepare')
            ->once()
            ->andReturn(new PreparedInvoiceResult(
                invoice: $this->sampleInvoice(),
                xml: '<Invoice/>',
                signedXml: '<SignedInvoice/>',
                finalXml: '<FinalInvoice/>',
                qrCode: 'qr',
                invoiceHash: 'hash',
                tenantConfig: \Maaz\LaravelZatca\DTOs\TenantConfig::fromArray(['tenant_id' => 'default'])
            ));
        $manager->shouldReceive('complianceCheck')
            ->once()
            ->andReturn([
                'success' => true,
                'status_code' => 200,
                'body' => [
                    'validationResults' => ['status' => 'PASS'],
                    'reportingStatus' => 'REPORTED',
                    'clearanceStatus' => null,
                ],
            ]);

        try {
            $this->artisan('zatca:compliance-check-sample', [
                '--compliance-response' => $responsePath,
                '--private-key' => $privateKeyPath,
            ])
                ->expectsOutputToContain('Compliance invoice check completed successfully.')
                ->expectsOutputToContain('PASS')
                ->assertExitCode(Command::SUCCESS);
        } finally {
            @unlink($privateKeyPath);
            @unlink($responsePath);
            @rmdir($workspace);
        }
    }

    private function sampleInvoice(): \Maaz\LaravelZatca\DTOs\InvoiceData
    {
        return \Maaz\LaravelZatca\DTOs\InvoiceData::fromArray([
            'invoice_number' => 'COMP-1',
            'uuid' => 'uuid',
            'issued_at' => now()->toIso8601String(),
            'seller' => [
                'name' => 'Seller',
                'vat_number' => '313138851500003',
            ],
            'items' => [
                [
                    'name' => 'Item',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_percent' => 15,
                ],
            ],
        ]);
    }
}
