<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\DTOs\SubmissionResult;
use Maaz\LaravelZatca\Services\ZatcaManager;
use Maaz\LaravelZatca\Tests\TestCase;

class SubmitSampleInvoiceCommandTest extends TestCase
{
    public function test_it_submits_a_sample_reporting_invoice_using_saved_production_credentials(): void
    {
        $workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-submit-' . uniqid('', true);
        mkdir($workspace, 0777, true);

        $responsePath = $workspace . DIRECTORY_SEPARATOR . 'production-csid.json';
        $privateKeyPath = $workspace . DIRECTORY_SEPARATOR . 'private-key.pem';

        file_put_contents($responsePath, json_encode([
            'body' => [
                'binarySecurityToken' => 'production-token',
                'secret' => 'production-secret',
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($privateKeyPath, 'private-key');

        $invoice = $this->sampleInvoice();
        $result = new SubmissionResult(
            invoice: $invoice,
            mode: 'reporting',
            xml: '<Invoice/>',
            signedXml: '<FinalInvoice/>',
            qrCode: 'qr',
            invoiceHash: 'hash',
            apiResponse: [
                'success' => true,
                'status_code' => 200,
                'body' => [
                    'reportingStatus' => 'REPORTED',
                    'clearanceStatus' => null,
                ],
            ],
            tenantConfig: \Maaz\LaravelZatca\DTOs\TenantConfig::fromArray(['tenant_id' => 'default'])
        );

        $manager = $this->mock(ZatcaManager::class);
        $manager->shouldReceive('invoice->invoiceNumber->issuedAt->seller->buyer->addItem->meta->generate')
            ->once()
            ->andReturn($invoice);
        $manager->shouldReceive('report')
            ->once()
            ->andReturn($result);
        $manager->shouldNotReceive('clearance');

        try {
            $this->artisan('zatca:submit-sample', [
                '--production-response' => $responsePath,
                '--private-key' => $privateKeyPath,
                '--mode' => 'reporting',
            ])
                ->expectsOutputToContain('Submission completed successfully.')
                ->expectsOutputToContain('REPORTED')
                ->assertExitCode(Command::SUCCESS);
        } finally {
            @unlink($privateKeyPath);
            @unlink($responsePath);
            @rmdir($workspace);
        }
    }

    public function test_it_submits_a_sample_clearance_invoice_using_saved_production_credentials(): void
    {
        $workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-clearance-' . uniqid('', true);
        mkdir($workspace, 0777, true);

        $responsePath = $workspace . DIRECTORY_SEPARATOR . 'production-csid.json';
        $privateKeyPath = $workspace . DIRECTORY_SEPARATOR . 'private-key.pem';

        file_put_contents($responsePath, json_encode([
            'body' => [
                'binarySecurityToken' => 'production-token',
                'secret' => 'production-secret',
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($privateKeyPath, 'private-key');

        $invoice = $this->sampleInvoice();
        $result = new SubmissionResult(
            invoice: $invoice,
            mode: 'clearance',
            xml: '<Invoice/>',
            signedXml: '<FinalInvoice/>',
            qrCode: 'qr',
            invoiceHash: 'hash',
            apiResponse: [
                'success' => true,
                'status_code' => 200,
                'body' => [
                    'reportingStatus' => null,
                    'clearanceStatus' => 'CLEARED',
                ],
            ],
            tenantConfig: \Maaz\LaravelZatca\DTOs\TenantConfig::fromArray(['tenant_id' => 'default'])
        );

        $manager = $this->mock(ZatcaManager::class);
        $manager->shouldReceive('invoice->invoiceNumber->issuedAt->seller->buyer->addItem->meta->generate')
            ->once()
            ->andReturn($invoice);
        $manager->shouldReceive('clearance')
            ->once()
            ->andReturn($result);
        $manager->shouldNotReceive('report');

        try {
            $this->artisan('zatca:submit-sample', [
                '--production-response' => $responsePath,
                '--private-key' => $privateKeyPath,
                '--mode' => 'clearance',
            ])
                ->expectsOutputToContain('Submission completed successfully.')
                ->expectsOutputToContain('CLEARED')
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
            'invoice_number' => 'SUBMIT-1',
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
