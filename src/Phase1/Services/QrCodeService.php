<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase1\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Maaz\LaravelZatca\Contracts\QrCodeGenerator;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Phase1\Encoders\TlvEncoder;

class QrCodeService implements QrCodeGenerator
{
    public function __construct(
        protected TlvEncoder $encoder
    ) {
    }

    public function generate(InvoiceData $invoice, TenantConfig $tenantConfig): string
    {
        $sellerName = $invoice->seller->name !== '' ? $invoice->seller->name : $tenantConfig->sellerName;
        $vatNumber = $invoice->seller->vatNumber !== '' ? $invoice->seller->vatNumber : $tenantConfig->sellerVatNumber;
        $issuedAt = $invoice->issuedAt !== ''
            ? CarbonImmutable::parse($invoice->issuedAt)->toIso8601String()
            : CarbonImmutable::now()->toIso8601String();

        if ($sellerName === '') {
            throw new InvalidArgumentException('Seller name is required for ZATCA QR generation.');
        }

        if ($vatNumber === '') {
            throw new InvalidArgumentException('Seller VAT number is required for ZATCA QR generation.');
        }

        $fields = [
            1 => $sellerName,
            2 => $vatNumber,
            3 => $issuedAt,
            4 => $this->formatAmount($invoice->totalAmount),
            5 => $this->formatAmount($invoice->taxAmount),
        ];

        return base64_encode($this->encoder->encode($fields));
    }

    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
