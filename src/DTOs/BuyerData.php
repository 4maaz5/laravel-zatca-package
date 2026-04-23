<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\DTOs;

final readonly class BuyerData
{
    public function __construct(
        public string $name,
        public ?string $vatNumber = null,
        public ?string $street = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $countryCode = 'SA',
        public array $meta = [],
        public ?string $buildingNumber = null,
        public ?string $district = null,
        public ?string $plotIdentification = null,
        public ?string $registrationNumber = null,
        public ?string $registrationScheme = 'CRN'
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            name: (string) ($attributes['name'] ?? ''),
            vatNumber: isset($attributes['vat_number']) ? (string) $attributes['vat_number'] : null,
            street: isset($attributes['street']) ? (string) $attributes['street'] : null,
            city: isset($attributes['city']) ? (string) $attributes['city'] : null,
            postalCode: isset($attributes['postal_code']) ? (string) $attributes['postal_code'] : null,
            countryCode: isset($attributes['country_code']) ? (string) $attributes['country_code'] : 'SA',
            meta: (array) ($attributes['meta'] ?? []),
            buildingNumber: isset($attributes['building_number']) ? (string) $attributes['building_number'] : null,
            district: (string) ($attributes['district'] ?? $attributes['city_subdivision'] ?? ''),
            plotIdentification: isset($attributes['plot_identification'])
                ? (string) $attributes['plot_identification']
                : (isset($attributes['additional_number']) ? (string) $attributes['additional_number'] : null),
            registrationNumber: (string) ($attributes['registration_number'] ?? $attributes['crn'] ?? ''),
            registrationScheme: (string) ($attributes['registration_scheme'] ?? 'CRN')
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'vat_number' => $this->vatNumber,
            'street' => $this->street,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'meta' => $this->meta,
            'building_number' => $this->buildingNumber,
            'district' => $this->district,
            'plot_identification' => $this->plotIdentification,
            'registration_number' => $this->registrationNumber,
            'registration_scheme' => $this->registrationScheme,
        ];
    }
}
