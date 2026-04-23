<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\DTOs;

final readonly class InvoiceData
{
    public function __construct(
        public ?string $invoiceNumber,
        public string $uuid,
        public string $issuedAt,
        public SellerData $seller,
        public ?BuyerData $buyer,
        public array $items,
        public float $subtotal,
        public float $taxAmount,
        public float $totalAmount,
        public string $currency = 'SAR',
        public string $taxCurrency = 'SAR',
        public ?string $type = null,
        public ?string $notes = null,
        public array $meta = []
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        $items = array_map(
            static fn (array|InvoiceItemData $item): InvoiceItemData => $item instanceof InvoiceItemData ? $item : InvoiceItemData::fromArray($item),
            (array) ($attributes['items'] ?? [])
        );

        $subtotal = isset($attributes['subtotal'])
            ? (float) $attributes['subtotal']
            : round(array_sum(array_map(static fn (InvoiceItemData $item): float => $item->subtotal(), $items)), 2);

        $taxAmount = isset($attributes['tax_amount'])
            ? (float) $attributes['tax_amount']
            : round(array_sum(array_map(static fn (InvoiceItemData $item): float => $item->taxAmount(), $items)), 2);

        return new self(
            invoiceNumber: isset($attributes['invoice_number']) ? (string) $attributes['invoice_number'] : null,
            uuid: (string) ($attributes['uuid'] ?? ''),
            issuedAt: (string) ($attributes['issued_at'] ?? ''),
            seller: ($attributes['seller'] ?? null) instanceof SellerData
                ? $attributes['seller']
                : SellerData::fromArray((array) ($attributes['seller'] ?? [])),
            buyer: ($attributes['buyer'] ?? null) instanceof BuyerData
                ? $attributes['buyer']
                : (! empty($attributes['buyer']) ? BuyerData::fromArray((array) $attributes['buyer']) : null),
            items: $items,
            subtotal: $subtotal,
            taxAmount: $taxAmount,
            totalAmount: isset($attributes['total_amount']) ? (float) $attributes['total_amount'] : round($subtotal + $taxAmount, 2),
            currency: (string) ($attributes['currency'] ?? 'SAR'),
            taxCurrency: (string) ($attributes['tax_currency'] ?? 'SAR'),
            type: isset($attributes['type']) ? (string) $attributes['type'] : null,
            notes: isset($attributes['notes']) ? (string) $attributes['notes'] : null,
            meta: (array) ($attributes['meta'] ?? [])
        );
    }

    public function toArray(): array
    {
        return [
            'invoice_number' => $this->invoiceNumber,
            'uuid' => $this->uuid,
            'issued_at' => $this->issuedAt,
            'seller' => $this->seller->toArray(),
            'buyer' => $this->buyer?->toArray(),
            'items' => array_map(
                static fn (InvoiceItemData $item): array => $item->toArray(),
                $this->items
            ),
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->taxAmount,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'tax_currency' => $this->taxCurrency,
            'type' => $this->type,
            'notes' => $this->notes,
            'meta' => $this->meta,
        ];
    }
}
