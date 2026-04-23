<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\Services\ZatcaManager;
use Maaz\LaravelZatca\Tests\TestCase;

class RequestProductionCsidCommandTest extends TestCase
{
    public function test_it_requests_production_csid_using_a_saved_compliance_response(): void
    {
        $responsePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-compliance-' . uniqid('', true) . '.json';
        file_put_contents($responsePath, json_encode([
            'body' => [
                'requestID' => 1234567890123,
                'binarySecurityToken' => 'compliance-token',
                'secret' => 'compliance-secret',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->mock(ZatcaManager::class, function ($mock): void {
            $mock->shouldReceive('onboardProductionCsid')
                ->once()
                ->with([
                    'compliance_request_id' => '1234567890123',
                    'binary_security_token' => 'compliance-token',
                    'secret' => 'compliance-secret',
                ])
                ->andReturn([
                    'success' => true,
                    'status_code' => 200,
                    'body' => [
                        'requestID' => 2234567890123,
                        'dispositionMessage' => 'ISSUED',
                        'binarySecurityToken' => 'production-token',
                        'secret' => 'production-secret',
                    ],
                ]);
        });

        try {
            $this->artisan('zatca:production-csid', [
                '--compliance-response' => $responsePath,
            ])
                ->expectsOutputToContain('Production CSID issued successfully.')
                ->expectsOutputToContain('2234567890123')
                ->expectsOutputToContain('production-token')
                ->assertExitCode(Command::SUCCESS);
        } finally {
            @unlink($responsePath);
        }
    }

    public function test_it_requires_compliance_credentials(): void
    {
        $this->artisan('zatca:production-csid')
            ->assertExitCode(Command::FAILURE);
    }
}
