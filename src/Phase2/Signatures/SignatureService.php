<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase2\Signatures;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Maaz\LaravelZatca\Contracts\HashGenerator;
use Maaz\LaravelZatca\Contracts\InvoiceSigner;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\SignatureException;
use Maaz\LaravelZatca\Phase2\Hashing\ZatcaInvoiceHashGenerator;
use Maaz\LaravelZatca\Support\CertificateLoader;
use OpenSSLAsymmetricKey;

class SignatureService implements InvoiceSigner
{
    private const NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const NS_SIG = 'urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2';

    private const NS_SAC = 'urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2';

    private const NS_SBC = 'urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2';

    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    private const ALGORITHM_C14N11 = 'http://www.w3.org/2006/12/xml-c14n11';

    private const ALGORITHM_ECDSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256';

    private const ALGORITHM_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';

    private const ALGORITHM_XPATH = 'http://www.w3.org/TR/1999/REC-xpath-19991116';

    public function __construct(
        protected CertificateLoader $certificateLoader,
        protected ?HashGenerator $hashGenerator = null
    ) {
        $this->hashGenerator ??= new ZatcaInvoiceHashGenerator();
    }

    public function sign(string $xml, TenantConfig $tenantConfig): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        if (! $document->loadXML($xml)) {
            throw new SignatureException((string) trans('zatca::exceptions.signature_invalid_xml'));
        }

        $privateKey = $this->certificateLoader->loadPrivateKey($tenantConfig);
        $certificate = $this->certificateLoader->loadCertificate($tenantConfig);

        if ($certificate === null) {
            throw new SignatureException((string) trans('zatca::exceptions.signature_failed'));
        }

        $this->removeExistingExtensions($document);
        $this->attachSignature($document, $privateKey, $certificate);

        return $document->saveXML() ?: throw new SignatureException((string) trans('zatca::exceptions.signature_render_failed'));
    }

    protected function attachSignature(DOMDocument $document, OpenSSLAsymmetricKey $privateKey, string $certificate): void
    {
        $root = $document->documentElement;

        if (! $root instanceof DOMElement) {
            throw new SignatureException((string) trans('zatca::exceptions.signature_missing_root'));
        }

        $this->declareNamespaces($root);

        $invoiceDigest = $this->hashGenerator->generate($document->saveXML() ?: '');
        $certificateData = $this->certificateData($certificate);

        $extensions = $document->createElementNS(self::NS_EXT, 'ext:UBLExtensions');
        $extension = $document->createElementNS(self::NS_EXT, 'ext:UBLExtension');
        $extension->appendChild($document->createElementNS(self::NS_EXT, 'ext:ExtensionURI', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades'));

        $content = $document->createElementNS(self::NS_EXT, 'ext:ExtensionContent');
        $ublDocumentSignatures = $document->createElementNS(self::NS_SIG, 'sig:UBLDocumentSignatures');
        $ublDocumentSignatures->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sac', self::NS_SAC);
        $ublDocumentSignatures->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sbc', self::NS_SBC);

        $signatureInformation = $document->createElementNS(self::NS_SAC, 'sac:SignatureInformation');
        $signatureInformation->appendChild($document->createElementNS(self::NS_CBC, 'cbc:ID', 'urn:oasis:names:specification:ubl:signature:1'));
        $signatureInformation->appendChild($document->createElementNS(self::NS_SBC, 'sbc:ReferencedSignatureID', 'urn:oasis:names:specification:ubl:signature:Invoice'));

        $signature = $document->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', 'signature');

        $object = $this->buildXadesObject($document, $certificateData);
        $signature->appendChild($object);

        $signatureInformation->appendChild($signature);
        $ublDocumentSignatures->appendChild($signatureInformation);
        $content->appendChild($ublDocumentSignatures);
        $extension->appendChild($content);
        $extensions->appendChild($extension);

        $root->insertBefore($extensions, $root->firstChild);

        $signedProperties = $this->firstElementByLocalName($object, 'SignedProperties');
        $signedPropertiesDigest = $this->digestNode($signedProperties);
        $signedInfo = $this->buildSignedInfo($document, $invoiceDigest, $signedPropertiesDigest);

        $signature->insertBefore($signedInfo, $object);

        $signatureValue = null;
        $canonicalSignedInfo = $signedInfo->C14N(false, false);

        if ($canonicalSignedInfo === false || ! openssl_sign($canonicalSignedInfo, $signatureValue, $privateKey, OPENSSL_ALGO_SHA256) || $signatureValue === null) {
            throw new SignatureException((string) trans('zatca::exceptions.signature_failed'));
        }

        $signature->insertBefore(
            $document->createElementNS(self::NS_DS, 'ds:SignatureValue', base64_encode($signatureValue)),
            $object
        );
        $signature->insertBefore($this->buildKeyInfo($document, $certificateData['normalized']), $object);
    }

    protected function buildSignedInfo(DOMDocument $document, string $invoiceDigest, string $signedPropertiesDigest): DOMElement
    {
        $signedInfo = $document->createElementNS(self::NS_DS, 'ds:SignedInfo');

        $canonicalizationMethod = $document->createElementNS(self::NS_DS, 'ds:CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', self::ALGORITHM_C14N11);
        $signedInfo->appendChild($canonicalizationMethod);

        $signatureMethod = $document->createElementNS(self::NS_DS, 'ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', self::ALGORITHM_ECDSA_SHA256);
        $signedInfo->appendChild($signatureMethod);

        $invoiceReference = $document->createElementNS(self::NS_DS, 'ds:Reference');
        $invoiceReference->setAttribute('Id', 'invoiceSignedData');
        $invoiceReference->setAttribute('URI', '');

        $transforms = $document->createElementNS(self::NS_DS, 'ds:Transforms');
        $this->appendXPathTransform($document, $transforms, 'not(//ancestor-or-self::ext:UBLExtensions)');
        $this->appendXPathTransform($document, $transforms, 'not(//ancestor-or-self::cac:Signature)');
        $this->appendXPathTransform($document, $transforms, "not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID='QR'])");

        $canonicalTransform = $document->createElementNS(self::NS_DS, 'ds:Transform');
        $canonicalTransform->setAttribute('Algorithm', self::ALGORITHM_C14N11);
        $transforms->appendChild($canonicalTransform);

        $invoiceReference->appendChild($transforms);
        $invoiceReference->appendChild($this->digestMethod($document));
        $invoiceReference->appendChild($document->createElementNS(self::NS_DS, 'ds:DigestValue', $invoiceDigest));
        $signedInfo->appendChild($invoiceReference);

        $propertiesReference = $document->createElementNS(self::NS_DS, 'ds:Reference');
        $propertiesReference->setAttribute('Type', 'http://www.w3.org/2000/09/xmldsig#SignatureProperties');
        $propertiesReference->setAttribute('URI', '#xadesSignedProperties');
        $propertiesReference->appendChild($this->digestMethod($document));
        $propertiesReference->appendChild($document->createElementNS(self::NS_DS, 'ds:DigestValue', $signedPropertiesDigest));
        $signedInfo->appendChild($propertiesReference);

        return $signedInfo;
    }

    protected function appendXPathTransform(DOMDocument $document, DOMElement $transforms, string $xpathExpression): void
    {
        $transform = $document->createElementNS(self::NS_DS, 'ds:Transform');
        $transform->setAttribute('Algorithm', self::ALGORITHM_XPATH);
        $transform->appendChild($document->createElementNS(self::NS_DS, 'ds:XPath', $xpathExpression));
        $transforms->appendChild($transform);
    }

    protected function buildXadesObject(DOMDocument $document, array $certificateData): DOMElement
    {
        $snippet = sprintf(
            '<ds:Object xmlns:ds="%s">'
            . '<xades:QualifyingProperties xmlns:xades="%s" Target="signature">'
            . '<xades:SignedProperties Id="xadesSignedProperties">'
            . '<xades:SignedSignatureProperties>'
            . '<xades:SigningTime>%s</xades:SigningTime>'
            . '<xades:SigningCertificate>'
            . '<xades:Cert>'
            . '<xades:CertDigest>'
            . '<ds:DigestMethod Algorithm="%s"/>'
            . '<ds:DigestValue>%s</ds:DigestValue>'
            . '</xades:CertDigest>'
            . '<xades:IssuerSerial>'
            . '<ds:X509IssuerName>%s</ds:X509IssuerName>'
            . '<ds:X509SerialNumber>%s</ds:X509SerialNumber>'
            . '</xades:IssuerSerial>'
            . '</xades:Cert>'
            . '</xades:SigningCertificate>'
            . '</xades:SignedSignatureProperties>'
            . '</xades:SignedProperties>'
            . '</xades:QualifyingProperties>'
            . '</ds:Object>',
            self::NS_DS,
            self::NS_XADES,
            CarbonImmutable::now((string) config('app.timezone', 'UTC'))->format('Y-m-d\TH:i:s'),
            self::ALGORITHM_SHA256,
            htmlspecialchars($certificateData['digest'], ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($certificateData['issuer'], ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($certificateData['serial'], ENT_XML1 | ENT_QUOTES, 'UTF-8')
        );

        $fragment = new DOMDocument('1.0', 'UTF-8');
        $fragment->preserveWhiteSpace = true;
        $fragment->formatOutput = false;

        if (! $fragment->loadXML($snippet)) {
            throw new SignatureException((string) trans('zatca::exceptions.signature_failed'));
        }

        $object = $document->importNode($fragment->documentElement, true);

        if (! $object instanceof DOMElement) {
            throw new SignatureException((string) trans('zatca::exceptions.signature_failed'));
        }

        return $object;
    }

    protected function buildKeyInfo(DOMDocument $document, string $normalizedCertificate): DOMElement
    {
        $keyInfo = $document->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $x509Data = $document->createElementNS(self::NS_DS, 'ds:X509Data');
        $x509Data->appendChild($document->createElementNS(self::NS_DS, 'ds:X509Certificate', $normalizedCertificate));
        $keyInfo->appendChild($x509Data);

        return $keyInfo;
    }

    protected function digestMethod(DOMDocument $document): DOMElement
    {
        $digestMethod = $document->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::ALGORITHM_SHA256);

        return $digestMethod;
    }

    protected function digestNode(DOMNode $node): string
    {
        $canonical = $node->C14N(false, false);

        if ($canonical === false) {
            throw new SignatureException((string) trans('zatca::exceptions.signature_failed'));
        }

        return base64_encode(hash('sha256', $canonical, true));
    }

    protected function firstElementByLocalName(DOMElement $element, string $localName): DOMElement
    {
        foreach ($element->getElementsByTagNameNS('*', $localName) as $node) {
            if ($node instanceof DOMElement) {
                return $node;
            }
        }

        throw new SignatureException((string) trans('zatca::exceptions.signature_failed'));
    }

    /**
     * @return array{normalized: string, digest: string, issuer: string, serial: string}
     */
    protected function certificateData(string $certificate): array
    {
        $normalized = $this->normalizeCertificate($certificate);
        $parsed = openssl_x509_parse($certificate);

        if ($parsed === false) {
            throw new SignatureException((string) trans('zatca::exceptions.signature_failed'));
        }

        return [
            'normalized' => $normalized,
            'digest' => base64_encode(hash('sha256', $normalized)),
            'issuer' => $this->formatIssuer((array) ($parsed['issuer'] ?? [])),
            'serial' => $this->resolveSerialNumber($parsed),
        ];
    }

    protected function formatIssuer(array $issuer): string
    {
        $parts = [];

        foreach (array_reverse($issuer, true) as $key => $value) {
            foreach (array_reverse((array) $value) as $item) {
                $parts[] = $key . '=' . $item;
            }
        }

        return implode(', ', $parts);
    }

    protected function resolveSerialNumber(array $parsedCertificate): string
    {
        $serialHex = $parsedCertificate['serialNumberHex'] ?? null;

        if (is_string($serialHex) && $serialHex !== '') {
            return $this->hexToDecimal($serialHex);
        }

        $serial = (string) ($parsedCertificate['serialNumber'] ?? '0');

        if (str_starts_with(strtolower($serial), '0x')) {
            return $this->hexToDecimal(substr($serial, 2));
        }

        return $serial;
    }

    protected function hexToDecimal(string $hex): string
    {
        $hex = ltrim(trim($hex), '0');

        if ($hex === '') {
            return '0';
        }

        $decimal = '0';

        foreach (str_split(strtolower($hex)) as $character) {
            $value = strpos('0123456789abcdef', $character);

            if ($value === false) {
                throw new SignatureException((string) trans('zatca::exceptions.signature_failed'));
            }

            $decimal = $this->multiplyDecimalString($decimal, 16);
            $decimal = $this->addDecimalStrings($decimal, (string) $value);
        }

        return $decimal;
    }

    protected function multiplyDecimalString(string $number, int $multiplier): string
    {
        $carry = 0;
        $result = '';

        foreach (array_reverse(str_split($number)) as $digit) {
            $product = ((int) $digit * $multiplier) + $carry;
            $result = (string) ($product % 10) . $result;
            $carry = intdiv($product, 10);
        }

        while ($carry > 0) {
            $result = (string) ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    protected function addDecimalStrings(string $left, string $right): string
    {
        $leftDigits = array_reverse(str_split($left));
        $rightDigits = array_reverse(str_split($right));
        $carry = 0;
        $result = '';
        $length = max(count($leftDigits), count($rightDigits));

        for ($index = 0; $index < $length; $index++) {
            $sum = ((int) ($leftDigits[$index] ?? 0)) + ((int) ($rightDigits[$index] ?? 0)) + $carry;
            $result = (string) ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }

        if ($carry > 0) {
            $result = (string) $carry . $result;
        }

        return ltrim($result, '0') ?: '0';
    }

    protected function removeExistingExtensions(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ext', self::NS_EXT);
        $nodes = $xpath->query('//*[local-name() = "UBLExtensions" and namespace-uri() = "' . self::NS_EXT . '"]');

        if ($nodes === false) {
            return;
        }

        foreach (iterator_to_array($nodes) as $node) {
            if ($node instanceof DOMNode) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    protected function declareNamespaces(DOMElement $root): void
    {
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::NS_EXT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sig', self::NS_SIG);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sac', self::NS_SAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sbc', self::NS_SBC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::NS_DS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', self::NS_XADES);
    }

    protected function normalizeCertificate(string $certificate): string
    {
        return str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"],
            '',
            $certificate
        );
    }
}
