<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase2\Qr;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use Maaz\LaravelZatca\Contracts\HashGenerator;
use Maaz\LaravelZatca\Contracts\Phase2QrCodeGenerator;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\CertificateException;
use Maaz\LaravelZatca\Phase1\Encoders\TlvEncoder;
use Maaz\LaravelZatca\Support\CertificateLoader;

class Phase2QrCodeService implements Phase2QrCodeGenerator
{
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    public function __construct(
        protected TlvEncoder $encoder,
        protected CertificateLoader $certificateLoader,
        protected HashGenerator $hashGenerator
    ) {
    }

    public function generate(
        InvoiceData $invoice,
        TenantConfig $tenantConfig,
        string $signedXml,
        ?string $invoiceHash = null
    ): string {
        $certificate = $this->resolveCertificate($tenantConfig, $signedXml);
        $signatureValue = $this->extractSignatureValue($signedXml);
        $publicKeyDer = $this->extractPublicKeyDer($certificate);
        $certificateSignature = $this->extractCertificateSignature($certificate);

        return $this->generateFromComponents(
            invoice: $invoice,
            tenantConfig: $tenantConfig,
            invoiceHash: $invoiceHash ?? $this->hashGenerator->generate($signedXml),
            signatureValue: $signatureValue,
            publicKeyDer: $publicKeyDer,
            certificateSignature: $certificateSignature
        );
    }

    public function generateFromComponents(
        InvoiceData $invoice,
        TenantConfig $tenantConfig,
        string $invoiceHash,
        string $signatureValue,
        string $publicKeyDer,
        ?string $certificateSignature = null
    ): string {
        $sellerName = $invoice->seller->name !== '' ? $invoice->seller->name : $tenantConfig->sellerName;
        $vatNumber = $invoice->seller->vatNumber !== '' ? $invoice->seller->vatNumber : $tenantConfig->sellerVatNumber;

        if ($sellerName === '') {
            throw new InvalidArgumentException('Seller name is required for ZATCA Phase 2 QR generation.');
        }

        if ($vatNumber === '') {
            throw new InvalidArgumentException('Seller VAT number is required for ZATCA Phase 2 QR generation.');
        }

        $fields = [
            1 => $sellerName,
            2 => $vatNumber,
            3 => $this->formatTimestamp($invoice),
            4 => $this->formatAmount($invoice->totalAmount),
            5 => $this->formatAmount($invoice->taxAmount),
            6 => $invoiceHash,
            7 => $signatureValue,
            8 => $publicKeyDer,
        ];

        if ($certificateSignature !== null && $certificateSignature !== '') {
            $fields[9] = $certificateSignature;
        }

        return base64_encode($this->encoder->encode($fields));
    }

    protected function resolveCertificate(TenantConfig $tenantConfig, string $signedXml): string
    {
        $certificate = $this->extractCertificateFromXml($signedXml)
            ?? $this->certificateLoader->loadCertificate($tenantConfig);

        if ($certificate === null) {
            throw new CertificateException((string) trans('zatca::exceptions.certificate_invalid_certificate'));
        }

        return $this->toCertificatePem($certificate);
    }

    protected function extractSignatureValue(string $signedXml): string
    {
        $document = $this->loadXml($signedXml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ds', self::NS_DS);

        $signatureValue = trim((string) $xpath->evaluate('string(//ds:SignatureValue[1])'));

        if ($signatureValue === '') {
            throw new InvalidArgumentException('Signed XML does not contain a ds:SignatureValue for Phase 2 QR generation.');
        }

        return $signatureValue;
    }

    protected function extractCertificateFromXml(string $signedXml): ?string
    {
        $document = $this->loadXml($signedXml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ds', self::NS_DS);

        $certificate = trim((string) $xpath->evaluate('string(//ds:X509Certificate[1])'));

        return $certificate !== '' ? $certificate : null;
    }

    protected function extractPublicKeyDer(string $certificate): string
    {
        $publicKey = openssl_pkey_get_public($certificate);

        if ($publicKey === false) {
            throw new CertificateException((string) trans('zatca::exceptions.certificate_invalid_certificate'));
        }

        $details = openssl_pkey_get_details($publicKey);

        if ($details === false || ! isset($details['key'])) {
            throw new CertificateException((string) trans('zatca::exceptions.certificate_invalid_certificate'));
        }

        $publicKeyDer = base64_decode($this->stripPem((string) $details['key']), true);

        if ($publicKeyDer === false) {
            throw new CertificateException((string) trans('zatca::exceptions.certificate_invalid_certificate'));
        }

        return $publicKeyDer;
    }

    protected function extractCertificateSignature(string $certificate): ?string
    {
        $der = base64_decode($this->stripPem($certificate), true);

        if ($der === false) {
            return null;
        }

        $offset = 0;
        $certificateSequence = $this->readDerElement($der, $offset);

        if ($certificateSequence['tag'] !== 0x30) {
            return null;
        }

        $innerOffset = 0;
        $certificateBody = $certificateSequence['value'];

        $this->readDerElement($certificateBody, $innerOffset);
        $this->readDerElement($certificateBody, $innerOffset);
        $signatureValue = $this->readDerElement($certificateBody, $innerOffset);

        if ($signatureValue['tag'] !== 0x03 || $signatureValue['value'] === '') {
            return null;
        }

        // The first byte in a DER BIT STRING is the unused-bits count.
        return substr($signatureValue['value'], 1);
    }

    /**
     * @return array{tag: int, value: string}
     */
    protected function readDerElement(string $der, int &$offset): array
    {
        if ($offset + 2 > strlen($der)) {
            throw new InvalidArgumentException('Invalid DER data while reading certificate signature.');
        }

        $tag = ord($der[$offset++]);
        $lengthByte = ord($der[$offset++]);

        if (($lengthByte & 0x80) === 0) {
            $length = $lengthByte;
        } else {
            $lengthBytes = $lengthByte & 0x7f;

            if ($lengthBytes === 0 || $offset + $lengthBytes > strlen($der)) {
                throw new InvalidArgumentException('Invalid DER length while reading certificate signature.');
            }

            $length = 0;

            for ($i = 0; $i < $lengthBytes; $i++) {
                $length = ($length << 8) | ord($der[$offset++]);
            }
        }

        if ($offset + $length > strlen($der)) {
            throw new InvalidArgumentException('Invalid DER value while reading certificate signature.');
        }

        $value = substr($der, $offset, $length);
        $offset += $length;

        return [
            'tag' => $tag,
            'value' => $value,
        ];
    }

    protected function loadXml(string $xml): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;

        if (! $document->loadXML($xml, LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('Unable to load signed XML for Phase 2 QR generation.');
        }

        return $document;
    }

    protected function toCertificatePem(string $certificate): string
    {
        if (str_contains($certificate, 'BEGIN CERTIFICATE')) {
            return $certificate;
        }

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(preg_replace('/\s+/', '', $certificate) ?? '', 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    protected function stripPem(string $pem): string
    {
        return preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----|\s+/', '', $pem) ?? '';
    }

    protected function formatTimestamp(InvoiceData $invoice): string
    {
        $issuedAt = $invoice->issuedAt !== ''
            ? CarbonImmutable::parse($invoice->issuedAt)
            : CarbonImmutable::now();

        return $issuedAt->format('Y-m-d\TH:i:s');
    }

    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
