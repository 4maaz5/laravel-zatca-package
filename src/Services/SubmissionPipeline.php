<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Maaz\LaravelZatca\Contracts\ApiClient;
use Maaz\LaravelZatca\Contracts\HashGenerator;
use Maaz\LaravelZatca\Contracts\InvoiceSigner;
use Maaz\LaravelZatca\Contracts\Phase2QrCodeGenerator;
use Maaz\LaravelZatca\Contracts\SubmissionPipeline as SubmissionPipelineContract;
use Maaz\LaravelZatca\Contracts\TenantInvoiceStateStore;
use Maaz\LaravelZatca\Contracts\XmlGenerator;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\PreparedInvoiceResult;
use Maaz\LaravelZatca\DTOs\SubmissionResult;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\ApiException;
use Maaz\LaravelZatca\Support\CertificateLoader;
use Maaz\LaravelZatca\Support\ZatcaLogger;
use RuntimeException;

class SubmissionPipeline implements SubmissionPipelineContract
{
    private const INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function __construct(
        protected XmlGenerator $xmlGenerator,
        protected InvoiceSigner $invoiceSigner,
        protected Phase2QrCodeGenerator $qrCodeGenerator,
        protected ApiClient $apiClient,
        protected HashGenerator $hashGenerator,
        protected ZatcaLogger $logger,
        protected CertificateLoader $certificateLoader,
        protected TenantInvoiceStateStore $tenantInvoiceStateStore
    ) {
    }

    public function prepare(InvoiceData $invoice, TenantConfig $tenantConfig): PreparedInvoiceResult
    {
        $xml = $this->xmlGenerator->generate($invoice, $tenantConfig);

        $this->logger->debug((string) trans('zatca::messages.log_xml_request'), [
            'tenant_id' => $tenantConfig->tenantId,
            'invoice_number' => $invoice->invoiceNumber,
            'uuid' => $invoice->uuid,
            'xml' => $xml,
        ]);

        $signedXml = $this->invoiceSigner->sign($xml, $tenantConfig);
        $invoiceHash = $this->extractInvoiceHash($signedXml) ?? $this->hashGenerator->generate($signedXml);
        $qrCode = $this->extractQrCode($signedXml);

        if ($qrCode === null) {
            $qrCode = $this->qrCodeGenerator->generate($invoice, $tenantConfig, $signedXml, $invoiceHash);
            $finalSignedXml = $this->injectQrCode($signedXml, $qrCode);
        } else {
            $finalSignedXml = $signedXml;
        }

        return new PreparedInvoiceResult(
            invoice: $invoice,
            xml: $xml,
            signedXml: $signedXml,
            finalXml: $finalSignedXml,
            qrCode: $qrCode,
            invoiceHash: $invoiceHash,
            tenantConfig: $tenantConfig
        );
    }

    public function submit(InvoiceData $invoice, TenantConfig $tenantConfig, string $mode): SubmissionResult
    {
        $preparedInvoice = $this->prepare($invoice, $tenantConfig);
        $this->assertAuthenticationCertificateMatchesInvoice($invoice, $tenantConfig);

        $this->logger->info((string) trans('zatca::messages.log_submitting_invoice'), [
            'tenant_id' => $tenantConfig->tenantId,
            'mode' => $mode,
            'invoice_number' => $invoice->invoiceNumber,
            'uuid' => $invoice->uuid,
        ]);

        $apiResponse = $this->apiClient->submit($preparedInvoice->apiPayload(), $tenantConfig, $mode);

        $this->logger->info((string) trans('zatca::messages.log_submission_complete'), [
            'tenant_id' => $tenantConfig->tenantId,
            'mode' => $mode,
            'invoice_number' => $invoice->invoiceNumber,
            'uuid' => $invoice->uuid,
            'status_code' => $apiResponse['status_code'] ?? null,
            'success' => $apiResponse['success'] ?? null,
        ]);

        if ((bool) ($apiResponse['success'] ?? false)) {
            $this->tenantInvoiceStateStore->persistSuccessfulSubmission(
                $invoice,
                $tenantConfig,
                $preparedInvoice->invoiceHash
            );
        }

        return new SubmissionResult(
            invoice: $invoice,
            mode: $mode,
            xml: $preparedInvoice->xml,
            signedXml: $preparedInvoice->finalXml,
            qrCode: $preparedInvoice->qrCode,
            invoiceHash: $preparedInvoice->invoiceHash,
            apiResponse: $apiResponse,
            tenantConfig: $tenantConfig
        );
    }

    private function assertAuthenticationCertificateMatchesInvoice(InvoiceData $invoice, TenantConfig $tenantConfig): void
    {
        $binarySecurityToken = (string) (
            $tenantConfig->api['binary_security_token']
            ?? $tenantConfig->api['client_id']
            ?? ''
        );

        if ($binarySecurityToken === '') {
            return;
        }

        $certificateVatNumber = $this->certificateLoader->extractVatNumber($binarySecurityToken);

        if ($certificateVatNumber === null || $certificateVatNumber === '') {
            return;
        }

        if ($invoice->seller->vatNumber !== '' && $invoice->seller->vatNumber !== $certificateVatNumber) {
            throw new ApiException((string) trans('zatca::exceptions.api_certificate_vat_mismatch', [
                'invoice_vat' => $invoice->seller->vatNumber,
                'certificate_vat' => $certificateVatNumber,
            ]));
        }
    }

    private function injectQrCode(string $signedXml, string $qrCode): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        if (! $document->loadXML($signedXml)) {
            throw new RuntimeException('Unable to parse signed invoice XML before QR injection.');
        }

        if (! $document->documentElement instanceof DOMElement) {
            throw new RuntimeException('Signed invoice XML is missing the root Invoice element.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('invoice', self::INVOICE_NS);
        $xpath->registerNamespace('cac', self::CAC_NS);
        $xpath->registerNamespace('cbc', self::CBC_NS);

        foreach ($xpath->query('/invoice:Invoice/cac:AdditionalDocumentReference[cbc:ID="QR"]') ?: [] as $existingQrReference) {
            $existingQrReference->parentNode?->removeChild($existingQrReference);
        }

        $invoice = $document->documentElement;
        $qrReference = $this->makeQrReference($document, $qrCode);
        $signature = $xpath->query('/invoice:Invoice/cac:Signature')->item(0);
        $supplierParty = $xpath->query('/invoice:Invoice/cac:AccountingSupplierParty')->item(0);

        if ($signature !== null) {
            $invoice->insertBefore($qrReference, $signature);
        } elseif ($supplierParty !== null) {
            $invoice->insertBefore($qrReference, $supplierParty);
        } else {
            $invoice->appendChild($qrReference);
        }

        $xml = $document->saveXML();

        if (! is_string($xml)) {
            throw new RuntimeException('Unable to serialize signed invoice XML after QR injection.');
        }

        return $xml;
    }

    private function extractInvoiceHash(string $signedXml): ?string
    {
        $xpath = $this->makeXPath($signedXml);

        if ($xpath === null) {
            return null;
        }

        $value = trim((string) $xpath->evaluate('string(//ds:Reference[@Id="invoiceSignedData"]/ds:DigestValue)'));

        return $value !== '' ? $value : null;
    }

    private function extractQrCode(string $signedXml): ?string
    {
        $xpath = $this->makeXPath($signedXml);

        if ($xpath === null) {
            return null;
        }

        $value = trim((string) $xpath->evaluate('string(/invoice:Invoice/cac:AdditionalDocumentReference[cbc:ID="QR"]/cac:Attachment/cbc:EmbeddedDocumentBinaryObject)'));

        return $value !== '' ? $value : null;
    }

    private function makeXPath(string $xml): ?DOMXPath
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        if (! $document->loadXML($xml)) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('invoice', self::INVOICE_NS);
        $xpath->registerNamespace('cac', self::CAC_NS);
        $xpath->registerNamespace('cbc', self::CBC_NS);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        return $xpath;
    }

    private function makeQrReference(DOMDocument $document, string $qrCode): DOMElement
    {
        $qrReference = $document->createElementNS(self::CAC_NS, 'cac:AdditionalDocumentReference');
        $qrReference->appendChild($document->createElementNS(self::CBC_NS, 'cbc:ID', 'QR'));

        $attachment = $document->createElementNS(self::CAC_NS, 'cac:Attachment');
        $binaryObject = $document->createElementNS(self::CBC_NS, 'cbc:EmbeddedDocumentBinaryObject', $qrCode);
        $binaryObject->setAttribute('mimeCode', 'text/plain');

        $attachment->appendChild($binaryObject);
        $qrReference->appendChild($attachment);

        return $qrReference;
    }
}
