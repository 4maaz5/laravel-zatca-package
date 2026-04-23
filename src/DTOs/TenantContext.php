<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\DTOs;

use InvalidArgumentException;

final readonly class TenantContext
{
    public function __construct(
        public string $id,
        public ?string $type = null,
        public ?string $key = null,
        public array $meta = []
    ) {
    }

    public static function fromMixed(mixed $tenant): self
    {
        if ($tenant instanceof self) {
            return $tenant;
        }

        if (is_string($tenant) || is_int($tenant)) {
            return new self((string) $tenant);
        }

        if (is_array($tenant)) {
            $id = $tenant['id'] ?? $tenant['tenant_id'] ?? $tenant['key'] ?? null;

            if ($id === null) {
                throw new InvalidArgumentException('Tenant array must contain an id, tenant_id, or key value.');
            }

            return new self(
                (string) $id,
                isset($tenant['type']) ? (string) $tenant['type'] : null,
                isset($tenant['key']) ? (string) $tenant['key'] : null,
                $tenant['meta'] ?? []
            );
        }

        if (is_object($tenant)) {
            $id = null;

            if (method_exists($tenant, 'getKey')) {
                $id = $tenant->getKey();
            } elseif (isset($tenant->id)) {
                $id = $tenant->id;
            } elseif (isset($tenant->tenant_id)) {
                $id = $tenant->tenant_id;
            }

            if ($id === null) {
                throw new InvalidArgumentException('Unable to resolve a tenant identifier from the given object.');
            }

            return new self(
                (string) $id,
                self::classBasename($tenant),
                property_exists($tenant, 'key') ? (string) $tenant->key : null,
                ['class' => $tenant::class]
            );
        }

        throw new InvalidArgumentException('Unsupported tenant value.');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'key' => $this->key,
            'meta' => $this->meta,
        ];
    }

    private static function classBasename(object $object): string
    {
        $class = $object::class;
        $segments = explode('\\', $class);

        return end($segments) ?: $class;
    }
}
