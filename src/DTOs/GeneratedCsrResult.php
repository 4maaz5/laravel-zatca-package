<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\DTOs;

final readonly class GeneratedCsrResult
{
    /**
     * @param array<string, string> $properties
     */
    public function __construct(
        public string $csrPath,
        public string $privateKeyPath,
        public string $csrBase64,
        public string $csrPem,
        public string $privateKeyPem,
        public array $properties,
        public ?string $configPath = null,
        public bool $rawOutput = false,
        public bool $simulation = false,
        public bool $nonProduction = false
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'csr_path' => $this->csrPath,
            'private_key_path' => $this->privateKeyPath,
            'csr_base64' => $this->csrBase64,
            'csr_pem' => $this->csrPem,
            'private_key_pem' => $this->privateKeyPem,
            'properties' => $this->properties,
            'config_path' => $this->configPath,
            'raw_output' => $this->rawOutput,
            'simulation' => $this->simulation,
            'non_production' => $this->nonProduction,
        ];
    }
}
