<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase2\Builders;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Maaz\LaravelZatca\Contracts\XmlGenerator;
use Maaz\LaravelZatca\DTOs\BuyerData;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\InvoiceItemData;
use Maaz\LaravelZatca\DTOs\SellerData;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use XMLWriter;

class UblInvoiceBuilder implements XmlGenerator
{
    private const DEFAULT_PIH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function generate(InvoiceData $invoice, TenantConfig $tenantConfig): string
    {
        if ($invoice->items === []) {
            throw new InvalidArgumentException('At least one invoice item is required to generate UBL XML.');
        }

        $issuedAt = $invoice->issuedAt !== ''
            ? CarbonImmutable::parse($invoice->issuedAt)
            : CarbonImmutable::now();

        $seller = $this->normalizeSeller($invoice->seller, $tenantConfig);
        $buyer = $invoice->buyer;
        $invoiceNumber = $invoice->invoiceNumber ?: $invoice->uuid;
        $uuid = $invoice->uuid !== '' ? $invoice->uuid : $invoice->invoiceNumber;
        $taxBreakdown = $this->buildTaxBreakdown($invoice->items);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);

        $xml->startElementNS(null, 'Invoice', self::NS_INVOICE);
        $xml->writeAttribute('xmlns:cac', self::NS_CAC);
        $xml->writeAttribute('xmlns:cbc', self::NS_CBC);

        $this->writeBasicElement($xml, 'cbc', 'ProfileID', 'reporting:1.0');
        $this->writeBasicElement($xml, 'cbc', 'ID', (string) $invoiceNumber);
        $this->writeBasicElement($xml, 'cbc', 'UUID', (string) $uuid);
        $this->writeBasicElement($xml, 'cbc', 'IssueDate', $issuedAt->format('Y-m-d'));
        $this->writeBasicElement($xml, 'cbc', 'IssueTime', $issuedAt->format('H:i:s'));
        $this->writeInvoiceTypeCode($xml, $invoice);
        $this->writeBasicElement($xml, 'cbc', 'DocumentCurrencyCode', $invoice->currency);
        $this->writeBasicElement($xml, 'cbc', 'TaxCurrencyCode', $invoice->taxCurrency);

        $this->writeAdditionalDocumentReferences($xml, $invoice, $tenantConfig);
        $this->writeSignatureReference($xml);
        $this->writeSupplierParty($xml, $seller);
        $this->writeCustomerParty($xml, $buyer);
        $this->writeDelivery($xml, $invoice, $issuedAt);
        $this->writePaymentMeans($xml, $invoice);
        $this->writeAllowanceCharge($xml, $invoice);
        $this->writeTaxTotals($xml, $invoice, $taxBreakdown);
        $this->writeMonetaryTotal($xml, $invoice);
        $this->writeInvoiceLines($xml, $invoice);

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    protected function writeSupplierParty(XMLWriter $xml, SellerData $seller): void
    {
        $xml->startElementNS('cac', 'AccountingSupplierParty', null);
        $xml->startElementNS('cac', 'Party', null);

        $this->writePartyIdentification(
            $xml,
            $seller->registrationNumber,
            $seller->registrationScheme ?? 'CRN'
        );

        $this->writePostalAddress(
            $xml,
            $seller->street,
            $seller->city,
            $seller->postalCode,
            $seller->countryCode ?? 'SA',
            $seller->buildingNumber,
            $seller->district,
            $seller->plotIdentification
        );

        $xml->startElementNS('cac', 'PartyTaxScheme', null);
        $this->writeBasicElement($xml, 'cbc', 'CompanyID', $seller->vatNumber);
        $xml->startElementNS('cac', 'TaxScheme', null);
        $this->writeBasicElement($xml, 'cbc', 'ID', 'VAT');
        $xml->endElement();
        $xml->endElement();

        $xml->startElementNS('cac', 'PartyLegalEntity', null);
        $this->writeBasicElement($xml, 'cbc', 'RegistrationName', $seller->name);
        $xml->endElement();

        $xml->endElement();
        $xml->endElement();
    }

    protected function writeCustomerParty(XMLWriter $xml, ?BuyerData $buyer): void
    {
        $xml->startElementNS('cac', 'AccountingCustomerParty', null);

        if (! $buyer instanceof BuyerData) {
            $xml->endElement();

            return;
        }

        $xml->startElementNS('cac', 'Party', null);

        $this->writePartyIdentification(
            $xml,
            $buyer->registrationNumber,
            $buyer->registrationScheme ?? 'CRN'
        );

        $this->writePostalAddress(
            $xml,
            $buyer->street,
            $buyer->city,
            $buyer->postalCode,
            $buyer->countryCode ?? 'SA',
            $buyer->buildingNumber,
            $buyer->district,
            $buyer->plotIdentification
        );

        if ($buyer->vatNumber !== null && $buyer->vatNumber !== '') {
            $xml->startElementNS('cac', 'PartyTaxScheme', null);
            $this->writeBasicElement($xml, 'cbc', 'CompanyID', $buyer->vatNumber);
            $xml->startElementNS('cac', 'TaxScheme', null);
            $this->writeBasicElement($xml, 'cbc', 'ID', 'VAT');
            $xml->endElement();
            $xml->endElement();
        }

        $xml->startElementNS('cac', 'PartyLegalEntity', null);
        $this->writeBasicElement($xml, 'cbc', 'RegistrationName', $buyer->name);
        $xml->endElement();

        $xml->endElement();
        $xml->endElement();
    }

    protected function writeAdditionalDocumentReferences(XMLWriter $xml, InvoiceData $invoice, TenantConfig $tenantConfig): void
    {
        $this->writeCounterReference(
            $xml,
            (string) ($invoice->meta['icv'] ?? $invoice->meta['invoice_counter_value'] ?? '1')
        );

        $this->writeBinaryReference(
            $xml,
            'PIH',
            (string) ($invoice->meta['pih'] ?? $invoice->meta['previous_invoice_hash'] ?? $tenantConfig->meta['pih'] ?? self::DEFAULT_PIH)
        );

        $qrCode = (string) ($invoice->meta['qr'] ?? $invoice->meta['qr_code'] ?? '');

        if ($qrCode !== '' || (bool) ($invoice->meta['include_qr_placeholder'] ?? false)) {
            $this->writeBinaryReference($xml, 'QR', $qrCode);
        }
    }

    protected function writeCounterReference(XMLWriter $xml, string $icv): void
    {
        $xml->startElementNS('cac', 'AdditionalDocumentReference', null);
        $this->writeBasicElement($xml, 'cbc', 'ID', 'ICV');
        $this->writeBasicElement($xml, 'cbc', 'UUID', $icv !== '' ? $icv : '1');
        $xml->endElement();
    }

    protected function writeBinaryReference(XMLWriter $xml, string $id, string $value): void
    {
        $xml->startElementNS('cac', 'AdditionalDocumentReference', null);
        $this->writeBasicElement($xml, 'cbc', 'ID', $id);
        $xml->startElementNS('cac', 'Attachment', null);
        $xml->startElementNS('cbc', 'EmbeddedDocumentBinaryObject', null);
        $xml->writeAttribute('mimeCode', 'text/plain');
        $xml->text($value);
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
    }

    protected function writeSignatureReference(XMLWriter $xml): void
    {
        $xml->startElementNS('cac', 'Signature', null);
        $this->writeBasicElement($xml, 'cbc', 'ID', 'urn:oasis:names:specification:ubl:signature:Invoice');
        $this->writeBasicElement($xml, 'cbc', 'SignatureMethod', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades');
        $xml->endElement();
    }

    protected function writeDelivery(XMLWriter $xml, InvoiceData $invoice, CarbonImmutable $issuedAt): void
    {
        if (! $this->isStandardInvoice($invoice)) {
            return;
        }

        $deliveryDate = (string) ($invoice->meta['delivery_date'] ?? $issuedAt->format('Y-m-d'));

        $xml->startElementNS('cac', 'Delivery', null);
        $this->writeBasicElement($xml, 'cbc', 'ActualDeliveryDate', $deliveryDate);
        $xml->endElement();
    }

    protected function writePaymentMeans(XMLWriter $xml, InvoiceData $invoice): void
    {
        $xml->startElementNS('cac', 'PaymentMeans', null);
        $this->writeBasicElement($xml, 'cbc', 'PaymentMeansCode', (string) ($invoice->meta['payment_means_code'] ?? '10'));

        if (isset($invoice->meta['payment_instruction_note'])) {
            $this->writeBasicElement($xml, 'cbc', 'InstructionNote', (string) $invoice->meta['payment_instruction_note']);
        }

        $xml->endElement();
    }

    protected function writeAllowanceCharge(XMLWriter $xml, InvoiceData $invoice): void
    {
        $xml->startElementNS('cac', 'AllowanceCharge', null);
        $this->writeBasicElement($xml, 'cbc', 'ChargeIndicator', 'false');
        $this->writeBasicElement($xml, 'cbc', 'AllowanceChargeReason', (string) ($invoice->meta['allowance_reason'] ?? 'discount'));
        $this->writeAmountElement($xml, 'cbc', 'Amount', (float) ($invoice->meta['allowance_total'] ?? 0), $invoice->currency);

        $xml->startElementNS('cac', 'TaxCategory', null);
        $this->writeTaxCategoryId($xml);
        $this->writeBasicElement($xml, 'cbc', 'Percent', $this->formatAmount($this->resolveDominantTaxPercent($invoice)));
        $this->writeTaxScheme($xml);
        $xml->endElement();

        $xml->endElement();
    }

    /**
     * @param array<int, array{taxable_amount: float, tax_amount: float, tax_percent: float}> $taxBreakdown
     */
    protected function writeTaxTotals(XMLWriter $xml, InvoiceData $invoice, array $taxBreakdown): void
    {
        $xml->startElementNS('cac', 'TaxTotal', null);
        $this->writeAmountElement($xml, 'cbc', 'TaxAmount', $invoice->taxAmount, $invoice->taxCurrency);
        $xml->endElement();

        $xml->startElementNS('cac', 'TaxTotal', null);
        $this->writeAmountElement($xml, 'cbc', 'TaxAmount', $invoice->taxAmount, $invoice->taxCurrency);
        foreach ($taxBreakdown as $breakdown) {
            $xml->startElementNS('cac', 'TaxSubtotal', null);
            $this->writeAmountElement($xml, 'cbc', 'TaxableAmount', $breakdown['taxable_amount'], $invoice->currency);
            $this->writeAmountElement($xml, 'cbc', 'TaxAmount', $breakdown['tax_amount'], $invoice->taxCurrency);

            $xml->startElementNS('cac', 'TaxCategory', null);
            $this->writeTaxCategoryId($xml);
            $this->writeBasicElement($xml, 'cbc', 'Percent', $this->formatAmount($breakdown['tax_percent']));
            $this->writeTaxScheme($xml);
            $xml->endElement();

            $xml->endElement();
        }

        $xml->endElement();
    }

    protected function writeMonetaryTotal(XMLWriter $xml, InvoiceData $invoice): void
    {
        $xml->startElementNS('cac', 'LegalMonetaryTotal', null);
        $this->writeAmountElement($xml, 'cbc', 'LineExtensionAmount', $invoice->subtotal, $invoice->currency);
        $this->writeAmountElement($xml, 'cbc', 'TaxExclusiveAmount', $invoice->subtotal, $invoice->currency);
        $this->writeAmountElement($xml, 'cbc', 'TaxInclusiveAmount', $invoice->totalAmount, $invoice->currency);
        $this->writeAmountElement($xml, 'cbc', 'AllowanceTotalAmount', (float) ($invoice->meta['allowance_total'] ?? 0), $invoice->currency);
        $this->writeAmountElement($xml, 'cbc', 'PrepaidAmount', (float) ($invoice->meta['prepaid_amount'] ?? 0), $invoice->currency);
        $this->writeAmountElement($xml, 'cbc', 'PayableAmount', $invoice->totalAmount, $invoice->currency);
        $xml->endElement();
    }

    protected function writeInvoiceLines(XMLWriter $xml, InvoiceData $invoice): void
    {
        foreach ($invoice->items as $index => $item) {
            $xml->startElementNS('cac', 'InvoiceLine', null);
            $this->writeBasicElement($xml, 'cbc', 'ID', (string) ($index + 1));

            $xml->startElementNS('cbc', 'InvoicedQuantity', null);
            $xml->writeAttribute('unitCode', $item->unitCode ?: 'PCE');
            $xml->text($this->formatQuantity($item->quantity));
            $xml->endElement();

            $this->writeAmountElement($xml, 'cbc', 'LineExtensionAmount', $item->subtotal(), $invoice->currency);

            $xml->startElementNS('cac', 'TaxTotal', null);
            $this->writeAmountElement($xml, 'cbc', 'TaxAmount', $item->taxAmount(), $invoice->taxCurrency);
            $this->writeAmountElement($xml, 'cbc', 'RoundingAmount', $item->total(), $invoice->currency);
            $xml->endElement();

            $xml->startElementNS('cac', 'Item', null);
            $this->writeBasicElement($xml, 'cbc', 'Name', $item->name);
            if ($item->description !== null && $item->description !== '') {
                $this->writeBasicElement($xml, 'cbc', 'Description', $item->description);
            }
            $xml->startElementNS('cac', 'ClassifiedTaxCategory', null);
            $this->writeTaxCategoryId($xml);
            $this->writeBasicElement($xml, 'cbc', 'Percent', $this->formatAmount($item->taxPercent));
            $this->writeTaxScheme($xml);
            $xml->endElement();
            $xml->endElement();

            $xml->startElementNS('cac', 'Price', null);
            $this->writeAmountElement($xml, 'cbc', 'PriceAmount', $item->unitPrice, $invoice->currency);
            $xml->endElement();

            $xml->endElement();
        }
    }

    protected function writePostalAddress(
        XMLWriter $xml,
        ?string $street,
        ?string $city,
        ?string $postalCode,
        string $countryCode,
        ?string $buildingNumber = null,
        ?string $district = null,
        ?string $plotIdentification = null
    ): void {
        $xml->startElementNS('cac', 'PostalAddress', null);
        $this->writeBasicElement($xml, 'cbc', 'StreetName', $street ?: 'N/A');
        if ($buildingNumber !== null && $buildingNumber !== '') {
            $this->writeBasicElement($xml, 'cbc', 'BuildingNumber', $buildingNumber);
        }
        if ($plotIdentification !== null && $plotIdentification !== '') {
            $this->writeBasicElement($xml, 'cbc', 'PlotIdentification', $plotIdentification);
        }
        if ($district !== null && $district !== '') {
            $this->writeBasicElement($xml, 'cbc', 'CitySubdivisionName', $district);
        }
        $this->writeBasicElement($xml, 'cbc', 'CityName', $city ?: 'N/A');
        if ($postalCode !== null && $postalCode !== '') {
            $this->writeBasicElement($xml, 'cbc', 'PostalZone', $postalCode);
        }
        $xml->startElementNS('cac', 'Country', null);
        $this->writeBasicElement($xml, 'cbc', 'IdentificationCode', $countryCode);
        $xml->endElement();
        $xml->endElement();
    }

    protected function writePartyIdentification(
        XMLWriter $xml,
        ?string $registrationNumber,
        ?string $scheme
    ): void {
        if ($registrationNumber === null || $registrationNumber === '') {
            return;
        }

        $xml->startElementNS('cac', 'PartyIdentification', null);
        $xml->startElementNS('cbc', 'ID', null);
        $xml->writeAttribute('schemeID', $scheme ?: 'CRN');
        $xml->text($registrationNumber);
        $xml->endElement();
        $xml->endElement();
    }

    protected function writeInvoiceTypeCode(XMLWriter $xml, InvoiceData $invoice): void
    {
        $xml->startElementNS('cbc', 'InvoiceTypeCode', null);
        $xml->writeAttribute('name', (string) ($invoice->meta['transaction_type_code'] ?? '0200000'));
        $xml->text($this->resolveInvoiceTypeCode($invoice));
        $xml->endElement();
    }

    protected function writeBasicElement(XMLWriter $xml, string $prefix, string $name, string $value): void
    {
        $xml->writeElementNS($prefix, $name, null, $value);
    }

    protected function writeAmountElement(XMLWriter $xml, string $prefix, string $name, float $value, string $currency): void
    {
        $xml->startElementNS($prefix, $name, null);
        $xml->writeAttribute('currencyID', $currency);
        $xml->text($this->formatAmount($value));
        $xml->endElement();
    }

    protected function writeTaxCategoryId(XMLWriter $xml, string $value = 'S'): void
    {
        $xml->startElementNS('cbc', 'ID', null);
        $xml->writeAttribute('schemeID', 'UN/ECE 5305');
        $xml->writeAttribute('schemeAgencyID', '6');
        $xml->text($value);
        $xml->endElement();
    }

    protected function writeTaxScheme(XMLWriter $xml): void
    {
        $xml->startElementNS('cac', 'TaxScheme', null);
        $xml->startElementNS('cbc', 'ID', null);
        $xml->writeAttribute('schemeID', 'UN/ECE 5153');
        $xml->writeAttribute('schemeAgencyID', '6');
        $xml->text('VAT');
        $xml->endElement();
        $xml->endElement();
    }

    protected function resolveDominantTaxPercent(InvoiceData $invoice): float
    {
        return $invoice->items[0]->taxPercent ?? 15.0;
    }

    protected function isStandardInvoice(InvoiceData $invoice): bool
    {
        return str_starts_with((string) ($invoice->meta['transaction_type_code'] ?? '0200000'), '01');
    }

    /**
     * @param array<int, InvoiceItemData> $items
     * @return array<int, array{taxable_amount: float, tax_amount: float, tax_percent: float}>
     */
    protected function buildTaxBreakdown(array $items): array
    {
        $breakdown = [];

        foreach ($items as $item) {
            $key = (string) $item->taxPercent;

            if (! isset($breakdown[$key])) {
                $breakdown[$key] = [
                    'taxable_amount' => 0.0,
                    'tax_amount' => 0.0,
                    'tax_percent' => $item->taxPercent,
                ];
            }

            $breakdown[$key]['taxable_amount'] += $item->subtotal();
            $breakdown[$key]['tax_amount'] += $item->taxAmount();
        }

        return array_values(array_map(
            fn (array $values): array => [
                'taxable_amount' => round((float) $values['taxable_amount'], 2),
                'tax_amount' => round((float) $values['tax_amount'], 2),
                'tax_percent' => round((float) $values['tax_percent'], 2),
            ],
            $breakdown
        ));
    }

    protected function resolveInvoiceTypeCode(InvoiceData $invoice): string
    {
        return $invoice->type ?: '388';
    }

    protected function normalizeSeller(SellerData $seller, TenantConfig $tenantConfig): SellerData
    {
        return new SellerData(
            name: $seller->name !== '' ? $seller->name : $tenantConfig->sellerName,
            vatNumber: $seller->vatNumber !== '' ? $seller->vatNumber : $tenantConfig->sellerVatNumber,
            street: $seller->street,
            city: $seller->city,
            postalCode: $seller->postalCode,
            countryCode: $seller->countryCode,
            meta: $seller->meta,
            buildingNumber: $seller->buildingNumber,
            district: $seller->district,
            plotIdentification: $seller->plotIdentification,
            registrationNumber: $seller->registrationNumber,
            registrationScheme: $seller->registrationScheme
        );
    }

    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    protected function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 2, '.', '');
    }
}
