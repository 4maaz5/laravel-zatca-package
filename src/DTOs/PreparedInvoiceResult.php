<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\DTOs;

final readonly class PreparedInvoiceResult
{
    public function __construct(
        public InvoiceData $invoice,
        public string $xml,
        public string $signedXml,
        public string $finalXml,
        public string $qrCode,
        public string $invoiceHash,
        public TenantConfig $tenantConfig
    ) {
    }

    public function apiPayload(): array
    {
        return [
            'invoiceHash' => $this->invoiceHash,
            'uuid' => $this->invoice->uuid,
            'invoice' => base64_encode($this->finalXml),
        ];
    }

    public function toArray(): array
    {
        return [
            'invoice' => $this->invoice->toArray(),
            'xml' => $this->xml,
            'signed_xml' => $this->signedXml,
            'final_xml' => $this->finalXml,
            'qr_code' => $this->qrCode,
            'invoice_hash' => $this->invoiceHash,
            'tenant' => $this->tenantConfig->toArray(),
            'api_payload' => $this->apiPayload(),
        ];
    }
}
