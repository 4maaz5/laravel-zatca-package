<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\DTOs;

final readonly class InvoiceItemData
{
    public function __construct(
        public string $name,
        public float $quantity,
        public float $unitPrice,
        public float $taxPercent,
        public float $discount = 0.0,
        public ?string $description = null,
        public ?string $unitCode = null,
        public array $meta = []
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            name: (string) ($attributes['name'] ?? ''),
            quantity: (float) ($attributes['quantity'] ?? 1),
            unitPrice: (float) ($attributes['unit_price'] ?? $attributes['unitPrice'] ?? 0),
            taxPercent: (float) ($attributes['tax_percent'] ?? $attributes['taxPercent'] ?? 0),
            discount: (float) ($attributes['discount'] ?? 0),
            description: isset($attributes['description']) ? (string) $attributes['description'] : null,
            unitCode: isset($attributes['unit_code']) ? (string) $attributes['unit_code'] : null,
            meta: (array) ($attributes['meta'] ?? [])
        );
    }

    public function subtotal(): float
    {
        return round(max(($this->quantity * $this->unitPrice) - $this->discount, 0), 2);
    }

    public function taxAmount(): float
    {
        return round($this->subtotal() * ($this->taxPercent / 100), 2);
    }

    public function total(): float
    {
        return round($this->subtotal() + $this->taxAmount(), 2);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'tax_percent' => $this->taxPercent,
            'discount' => $this->discount,
            'unit_code' => $this->unitCode,
            'subtotal' => $this->subtotal(),
            'tax_amount' => $this->taxAmount(),
            'total' => $this->total(),
            'meta' => $this->meta,
        ];
    }
}
