<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Maaz\LaravelZatca\DTOs\BuyerData;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\InvoiceItemData;
use Maaz\LaravelZatca\DTOs\PreparedInvoiceResult;
use Maaz\LaravelZatca\DTOs\SellerData;
use Maaz\LaravelZatca\DTOs\SubmissionResult;

class InvoiceBuilder
{
    protected ?SellerData $seller = null;

    protected ?BuyerData $buyer = null;

    /** @var array<int, InvoiceItemData> */
    protected array $items = [];

    protected ?string $invoiceNumber = null;

    protected ?string $issuedAt = null;

    protected string $currency = 'SAR';

    protected string $taxCurrency = 'SAR';

    protected ?string $type = null;

    protected ?string $notes = null;

    protected array $meta = [];

    public function __construct(
        protected ZatcaManager $manager,
        array|InvoiceData $seed = []
    ) {
        $this->fillFromInvoice($seed instanceof InvoiceData ? $seed : InvoiceData::fromArray($seed + ['seller' => $seed['seller'] ?? ['name' => '', 'vat_number' => '']]));
    }

    public function seller(array|SellerData $seller): self
    {
        $this->seller = $seller instanceof SellerData ? $seller : SellerData::fromArray($seller);

        return $this;
    }

    public function buyer(array|BuyerData $buyer): self
    {
        $this->buyer = $buyer instanceof BuyerData ? $buyer : BuyerData::fromArray($buyer);

        return $this;
    }

    public function addItem(array|InvoiceItemData $item): self
    {
        $this->items[] = $item instanceof InvoiceItemData ? $item : InvoiceItemData::fromArray($item);

        return $this;
    }

    public function invoiceNumber(string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    public function issuedAt(DateTimeInterface|string $issuedAt): self
    {
        $this->issuedAt = $issuedAt instanceof DateTimeInterface
            ? CarbonImmutable::instance($issuedAt)->toIso8601String()
            : CarbonImmutable::parse($issuedAt)->toIso8601String();

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function taxCurrency(string $currency): self
    {
        $this->taxCurrency = strtoupper($currency);

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function notes(string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function meta(array $meta): self
    {
        $this->meta = array_replace_recursive($this->meta, $meta);

        return $this;
    }

    public function generate(): InvoiceData
    {
        $totals = $this->calculateTotals();

        $invoice = new InvoiceData(
            invoiceNumber: $this->invoiceNumber,
            uuid: $this->manager->uuid(),
            issuedAt: $this->issuedAt ?? CarbonImmutable::now()->toIso8601String(),
            seller: $this->seller ?? SellerData::fromArray([]),
            buyer: $this->buyer,
            items: $this->items,
            subtotal: $totals['subtotal'],
            taxAmount: $totals['tax_amount'],
            totalAmount: $totals['total_amount'],
            currency: $this->currency,
            taxCurrency: $this->taxCurrency,
            type: $this->type,
            notes: $this->notes,
            meta: $this->meta
        );

        $this->manager->validate($invoice);

        return $invoice;
    }

    public function toDto(): InvoiceData
    {
        return $this->generate();
    }

    public function toArray(): array
    {
        return $this->generate()->toArray();
    }

    public function generateQr(): string
    {
        return $this->manager->generateQr($this->generate());
    }

    public function generateXml(): string
    {
        return $this->manager->generateXml($this->generate());
    }

    public function sign(): string
    {
        return $this->manager->sign($this->generate());
    }

    public function prepare(): PreparedInvoiceResult
    {
        return $this->manager->prepare($this->generate());
    }

    public function submit(string $mode = 'clearance'): SubmissionResult
    {
        return $this->manager->submit($this->generate(), $mode);
    }

    public function clearance(): SubmissionResult
    {
        return $this->submit('clearance');
    }

    public function report(): SubmissionResult
    {
        return $this->submit('reporting');
    }

    protected function calculateTotals(): array
    {
        $subtotal = round(array_sum(array_map(
            static fn (InvoiceItemData $item): float => $item->subtotal(),
            $this->items
        )), 2);

        $taxAmount = round(array_sum(array_map(
            static fn (InvoiceItemData $item): float => $item->taxAmount(),
            $this->items
        )), 2);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => round($subtotal + $taxAmount, 2),
        ];
    }

    protected function fillFromInvoice(InvoiceData $invoice): void
    {
        if ($invoice->seller->name !== '' || $invoice->seller->vatNumber !== '') {
            $this->seller = $invoice->seller;
        }

        $this->buyer = $invoice->buyer;
        $this->items = $invoice->items;
        $this->invoiceNumber = $invoice->invoiceNumber;
        $this->issuedAt = $invoice->issuedAt !== '' ? $invoice->issuedAt : null;
        $this->currency = $invoice->currency;
        $this->taxCurrency = $invoice->taxCurrency;
        $this->type = $invoice->type;
        $this->notes = $invoice->notes;
        $this->meta = $invoice->meta;
    }
}
