<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\Contracts\CsrGenerator;
use Maaz\LaravelZatca\DTOs\GeneratedCsrResult;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Tests\TestCase;

class GenerateCsrCommandTest extends TestCase
{
    public function test_it_renders_a_success_summary_for_generated_assets(): void
    {
        $this->app->bind(CsrGenerator::class, fn (): CsrGenerator => new class implements CsrGenerator {
            public function generate(array $payload, TenantConfig $tenantConfig): GeneratedCsrResult
            {
                return new GeneratedCsrResult(
                    csrPath: 'F:\\sandbox\\generated.csr',
                    privateKeyPath: 'F:\\sandbox\\generated.key',
                    csrBase64: 'Q1NSQkFTRTY0',
                    csrPem: "-----BEGIN CERTIFICATE REQUEST-----\nQ1NSQkFTRTY0\n-----END CERTIFICATE REQUEST-----\n",
                    privateKeyPem: "-----BEGIN EC PRIVATE KEY-----\nUFJJVkFURUtFWQ==\n-----END EC PRIVATE KEY-----\n",
                    properties: ['csr.common.name' => 'TST-123'],
                    configPath: 'F:\\sandbox\\csr.properties'
                );
            }
        });

        $this->artisan('zatca:csr-generate', [
            '--common-name' => 'TST-123',
            '--serial-number' => '1-TST|2-TST|3-uuid',
            '--location-address' => 'RRRD2929',
            '--industry-business-category' => 'Supply activities',
            '--show-csr' => true,
        ])
            ->expectsOutputToContain('CSR and private key generated successfully.')
            ->expectsOutputToContain('F:\\sandbox\\generated.csr')
            ->expectsOutputToContain('Q1NSQkFTRTY0')
            ->assertExitCode(Command::SUCCESS);
    }
}
