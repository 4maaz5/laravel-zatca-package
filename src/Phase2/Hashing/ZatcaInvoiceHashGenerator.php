<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase2\Hashing;

use DOMDocument;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;
use Maaz\LaravelZatca\Contracts\HashGenerator;
use RuntimeException;

class ZatcaInvoiceHashGenerator implements HashGenerator
{
    private const NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function generate(string $payload): string
    {
        return base64_encode(hash('sha256', $this->canonicalizeForHashing($payload), true));
    }

    public function canonicalizeForHashing(string $xml): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        if (! $document->loadXML($xml)) {
            throw new InvalidArgumentException('Unable to generate ZATCA invoice hash because the XML is invalid.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ext', self::NS_EXT);
        $xpath->registerNamespace('cac', self::NS_CAC);
        $xpath->registerNamespace('cbc', self::NS_CBC);

        // ZATCA hashes the invoice after removing mutable signature and QR blocks.
        $this->removeNodes($xpath, '//*[local-name() = "UBLExtensions" and namespace-uri() = "' . self::NS_EXT . '"]');
        $this->removeNodes(
            $xpath,
            '//*[local-name() = "AdditionalDocumentReference" and namespace-uri() = "' . self::NS_CAC . '"]'
            . '[*[local-name() = "ID" and namespace-uri() = "' . self::NS_CBC . '" and normalize-space(.) = "QR"]]'
        );
        $this->removeNodes($xpath, '//*[local-name() = "Signature" and namespace-uri() = "' . self::NS_CAC . '"]');

        $canonical = $document->C14N(false, false);

        if ($canonical === false) {
            throw new RuntimeException('Unable to canonicalize invoice XML for ZATCA hashing.');
        }

        return $canonical;
    }

    protected function removeNodes(DOMXPath $xpath, string $query): void
    {
        $nodes = $xpath->query($query);

        if ($nodes === false) {
            return;
        }

        /** @var array<int, DOMNode> $detachedNodes */
        $detachedNodes = iterator_to_array($nodes);

        foreach ($detachedNodes as $node) {
            $node->parentNode?->removeChild($node);
        }
    }
}
