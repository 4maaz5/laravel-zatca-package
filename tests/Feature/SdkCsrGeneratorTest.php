<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\CsrException;
use Maaz\LaravelZatca\Phase2\Onboarding\SdkCsrGenerator;
use Maaz\LaravelZatca\Tests\TestCase;

class SdkCsrGeneratorTest extends TestCase
{
    public function test_it_generates_pem_outputs_from_payload_and_tenant_defaults(): void
    {
        $workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-csr-test-' . uniqid('', true);
        mkdir($workspace, 0777, true);

        $generator = new class($workspace) extends SdkCsrGenerator {
            /** @var array<int, string> */
            public array $capturedCommand = [];

            public function __construct(private readonly string $workspace)
            {
                parent::__construct();
            }

            protected function resolveCliPath(): string
            {
                return $this->workspace . DIRECTORY_SEPARATOR . 'fatooraNet.exe';
            }

            protected function makeWorkspace(): string
            {
                return $this->workspace;
            }

            protected function cleanupWorkspace(string $workspace): void
            {
            }

            protected function runSdkCommand(array $command, string $workingDirectory): array
            {
                $this->capturedCommand = $command;

                $csrPath = $command[array_search('-generatedCsr', $command, true) + 1];
                $privateKeyPath = $command[array_search('-privateKey', $command, true) + 1];

                file_put_contents($csrPath, "-----BEGIN CERTIFICATE REQUEST-----\nQ1NSQkFTRTY0\n-----END CERTIFICATE REQUEST-----\n");
                file_put_contents($privateKeyPath, "-----BEGIN EC PRIVATE KEY-----\nUFJJVkFURUtFWQ==\n-----END EC PRIVATE KEY-----\n");

                return [
                    'stdout' => 'Operation Completed Successfully!',
                    'stderr' => '',
                    'exit_code' => 0,
                ];
            }
        };

        try {
            $result = $generator->generate([
                'common_name' => 'TST-886431145-399999999900003',
                'serial_number' => '1-TST|2-TST|3-ed22f1d8-e6a2-1118-9b58-d9a8f11e445f',
                'location_address' => 'RRRD2929',
                'industry_business_category' => 'Supply activities',
            ], TenantConfig::fromArray([
                'tenant_id' => 'tenant-1',
                'environment' => 'sandbox',
                'seller_name' => 'Maximum Speed Tech Supply LTD',
                'seller_vat_number' => '399999999900003',
                'branch_name' => 'Riyadh Branch',
            ]));

            $this->assertSame(base64_encode("-----BEGIN CERTIFICATE REQUEST-----\nQ1NSQkFTRTY0\n-----END CERTIFICATE REQUEST-----\n"), $result->csrBase64);
            $this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $result->csrPem);
            $this->assertStringContainsString('BEGIN EC PRIVATE KEY', $result->privateKeyPem);
            $this->assertSame('399999999900003', $result->properties['csr.organization.identifier']);
            $this->assertSame('Riyadh Branch', $result->properties['csr.organization.unit.name']);
            $this->assertContains('-pem', $generator->capturedCommand);
        } finally {
            foreach (glob($workspace . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($workspace);
        }
    }

    public function test_it_requires_missing_properties_when_no_config_file_is_provided(): void
    {
        $generator = new SdkCsrGenerator();

        $this->expectException(CsrException::class);

        $generator->generate([], TenantConfig::fromArray([
            'tenant_id' => 'tenant-1',
            'environment' => 'sandbox',
            'seller_name' => 'Seller',
            'seller_vat_number' => '300000000000003',
        ]));
    }
}
