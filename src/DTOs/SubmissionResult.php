<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\DTOs;

final readonly class SubmissionResult
{
    public function __construct(
        public InvoiceData $invoice,
        public string $mode,
        public string $xml,
        public string $signedXml,
        public string $qrCode,
        public string $invoiceHash,
        public array $apiResponse,
        public TenantConfig $tenantConfig
    ) {
    }

    public function accepted(): bool
    {
        return (bool) ($this->apiResponse['success'] ?? false);
    }

    public function toArray(): array
    {
        return [
            'invoice' => $this->invoice->toArray(),
            'mode' => $this->mode,
            'xml' => $this->xml,
            'signed_xml' => $this->signedXml,
            'qr_code' => $this->qrCode,
            'invoice_hash' => $this->invoiceHash,
            'api_response' => $this->apiResponse,
            'tenant' => $this->tenantConfig->toArray(),
            'accepted' => $this->accepted(),
        ];
    }
}
