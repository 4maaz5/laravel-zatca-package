<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\DTOs;

final readonly class TenantConfig
{
    public function __construct(
        public string $tenantId,
        public string $environment,
        public string $sellerName,
        public string $sellerVatNumber,
        public ?string $branchName,
        public string $language,
        public array $certificates,
        public array $api,
        public array $features,
        public array $meta = []
    ) {
    }

    public static function fromArray(array $config, ?TenantContext $tenant = null): self
    {
        return new self(
            tenantId: (string) ($config['tenant_id'] ?? $tenant?->id ?? 'default'),
            environment: (string) ($config['environment'] ?? 'sandbox'),
            sellerName: (string) ($config['seller_name'] ?? ''),
            sellerVatNumber: (string) ($config['seller_vat_number'] ?? ''),
            branchName: isset($config['branch_name']) ? (string) $config['branch_name'] : null,
            language: (string) ($config['language'] ?? 'en'),
            certificates: (array) ($config['certificates'] ?? []),
            api: (array) ($config['api'] ?? []),
            features: (array) ($config['features'] ?? []),
            meta: (array) ($config['meta'] ?? [])
        );
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'environment' => $this->environment,
            'seller_name' => $this->sellerName,
            'seller_vat_number' => $this->sellerVatNumber,
            'branch_name' => $this->branchName,
            'language' => $this->language,
            'certificates' => $this->certificates,
            'api' => $this->api,
            'features' => $this->features,
            'meta' => $this->meta,
        ];
    }
}
