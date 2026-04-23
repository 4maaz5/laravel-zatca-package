<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\Services\ZatcaManager;
use Maaz\LaravelZatca\Tests\TestCase;

class RequestComplianceCsidCommandTest extends TestCase
{
    public function test_it_requests_compliance_csid_using_a_csr_file(): void
    {
        $csrPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-csr-' . uniqid('', true) . '.csr';
        file_put_contents($csrPath, "-----BEGIN CERTIFICATE REQUEST-----\nQ1NSQkFTRTY0\n-----END CERTIFICATE REQUEST-----\n");

        $this->mock(ZatcaManager::class, function ($mock) use ($csrPath): void {
            $mock->shouldReceive('onboardComplianceCsid')
                ->once()
                ->with([
                    'otp' => '123345',
                    'csr' => base64_encode("-----BEGIN CERTIFICATE REQUEST-----\nQ1NSQkFTRTY0\n-----END CERTIFICATE REQUEST-----\n"),
                ])
                ->andReturn([
                    'success' => true,
                    'status_code' => 200,
                    'body' => [
                        'requestID' => 1234567890123,
                        'dispositionMessage' => 'ISSUED',
                        'binarySecurityToken' => 'token',
                        'secret' => 'secret',
                    ],
                ]);
        });

        try {
            $this->artisan('zatca:compliance-csid', [
                'otp' => '123345',
                '--csr-file' => $csrPath,
            ])
                ->expectsOutputToContain('Compliance CSID issued successfully.')
                ->expectsOutputToContain('1234567890123')
                ->expectsOutputToContain('token')
                ->assertExitCode(Command::SUCCESS);
        } finally {
            @unlink($csrPath);
        }
    }

    public function test_it_requires_a_single_csr_input_source(): void
    {
        $this->artisan('zatca:compliance-csid', [
            'otp' => '123345',
        ])->assertExitCode(Command::FAILURE);
    }
}
