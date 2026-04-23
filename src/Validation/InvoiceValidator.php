<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Validation;

use Maaz\LaravelZatca\Contracts\InvoiceValidator as InvoiceValidatorContract;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\InvoiceItemData;
use Maaz\LaravelZatca\Exceptions\ValidationException;

class InvoiceValidator implements InvoiceValidatorContract
{
    public function validate(InvoiceData $invoice): array
    {
        $errors = [];

        if ($invoice->seller->name === '') {
            $errors[] = (string) trans('zatca::validation.seller_name_required');
        }

        if ($invoice->seller->vatNumber === '') {
            $errors[] = (string) trans('zatca::validation.seller_vat_required');
        } elseif (! preg_match('/^\d{15}$/', $invoice->seller->vatNumber)) {
            $errors[] = (string) trans('zatca::validation.seller_vat_invalid');
        }

        if ($invoice->items === []) {
            $errors[] = (string) trans('zatca::validation.items_required');
        }

        foreach ($invoice->items as $index => $item) {
            $lineNumber = $index + 1;

            if (! $item instanceof InvoiceItemData) {
                $errors[] = (string) trans('zatca::validation.item_invalid', ['line' => $lineNumber]);
                continue;
            }

            if ($item->name === '') {
                $errors[] = (string) trans('zatca::validation.item_name_required', ['line' => $lineNumber]);
            }

            if ($item->quantity <= 0) {
                $errors[] = (string) trans('zatca::validation.item_quantity_invalid', ['line' => $lineNumber]);
            }

            if ($item->unitPrice < 0) {
                $errors[] = (string) trans('zatca::validation.item_unit_price_invalid', ['line' => $lineNumber]);
            }

            if ($item->taxPercent < 0) {
                $errors[] = (string) trans('zatca::validation.item_tax_percent_invalid', ['line' => $lineNumber]);
            }
        }

        $calculatedSubtotal = round(array_sum(array_map(
            static fn (InvoiceItemData $item): float => $item->subtotal(),
            $invoice->items
        )), 2);

        $calculatedTaxAmount = round(array_sum(array_map(
            static fn (InvoiceItemData $item): float => $item->taxAmount(),
            $invoice->items
        )), 2);

        $calculatedTotalAmount = round($calculatedSubtotal + $calculatedTaxAmount, 2);

        if (round($invoice->subtotal, 2) !== $calculatedSubtotal) {
            $errors[] = (string) trans('zatca::validation.subtotal_mismatch', [
                'expected' => number_format($calculatedSubtotal, 2, '.', ''),
                'actual' => number_format($invoice->subtotal, 2, '.', ''),
            ]);
        }

        if (round($invoice->taxAmount, 2) !== $calculatedTaxAmount) {
            $errors[] = (string) trans('zatca::validation.tax_amount_mismatch', [
                'expected' => number_format($calculatedTaxAmount, 2, '.', ''),
                'actual' => number_format($invoice->taxAmount, 2, '.', ''),
            ]);
        }

        if (round($invoice->totalAmount, 2) !== $calculatedTotalAmount) {
            $errors[] = (string) trans('zatca::validation.total_amount_mismatch', [
                'expected' => number_format($calculatedTotalAmount, 2, '.', ''),
                'actual' => number_format($invoice->totalAmount, 2, '.', ''),
            ]);
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'valid' => true,
            'errors' => [],
            'invoice' => $invoice->toArray(),
        ];
    }
}
