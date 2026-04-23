<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Maaz\LaravelZatca\Contracts\InvoiceNormalizer as InvoiceNormalizerContract;
use Maaz\LaravelZatca\Contracts\InvoiceValidator;
use Maaz\LaravelZatca\DTOs\InvoiceData;

class InvoiceNormalizer implements InvoiceNormalizerContract
{
    public function __construct(
        protected InvoiceValidator $invoiceValidator
    ) {
    }

    public function normalize(array|InvoiceData $invoice): InvoiceData
    {
        $normalizedInvoice = $invoice instanceof InvoiceData
            ? $invoice
            : InvoiceData::fromArray($invoice + ['seller' => $invoice['seller'] ?? ['name' => '', 'vat_number' => '']]);

        if ($normalizedInvoice->issuedAt === '' || $normalizedInvoice->uuid === '') {
            $normalizedInvoice = InvoiceData::fromArray([
                ...$normalizedInvoice->toArray(),
                'issued_at' => $normalizedInvoice->issuedAt !== '' ? $normalizedInvoice->issuedAt : CarbonImmutable::now()->toIso8601String(),
                'uuid' => $normalizedInvoice->uuid !== '' ? $normalizedInvoice->uuid : (string) Str::uuid(),
            ]);
        }

        $this->invoiceValidator->validate($normalizedInvoice);

        return $normalizedInvoice;
    }
}
