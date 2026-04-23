<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\Tests\TestCase;

class XmlGenerationTest extends TestCase
{
    private const VALID_PIH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function test_it_generates_ubl_invoice_xml(): void
    {
        $invoice = Zatca::invoice()
            ->invoiceNumber('INV-1003')
            ->issuedAt('2026-04-13T10:30:00+03:00')
            ->seller([
                'name' => 'Maaz Store',
                'vat_number' => '300000000000003',
                'crn' => '1010010000',
                'street' => 'King Rd',
                'building_number' => '2322',
                'additional_number' => '1234',
                'district' => 'Al-Murabba',
                'city' => 'Riyadh',
                'postal_code' => '12345',
            ])
            ->buyer([
                'name' => 'Buyer Co',
                'vat_number' => '300000000000013',
                'crn' => '1010010001',
                'street' => 'Buyer St',
                'building_number' => '1111',
                'additional_number' => '5678',
                'district' => 'Al-Murooj',
                'city' => 'Jeddah',
                'postal_code' => '54321',
            ])
            ->addItem([
                'name' => 'Product A',
                'quantity' => 2,
                'unit_price' => 100,
                'tax_percent' => 15,
            ])
            ->meta([
                'icv' => '23',
                'pih' => self::VALID_PIH,
            ])
            ->generate();

        $xml = Zatca::generateXml($invoice);

        $document = new DOMDocument();
        $document->loadXML($xml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('invoice', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $this->assertSame('Invoice', $document->documentElement?->localName);
        $this->assertSame('INV-1003', $xpath->evaluate('string(/invoice:Invoice/cbc:ID)'));
        $this->assertSame('Maaz Store', $xpath->evaluate('string(/invoice:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName)'));
        $this->assertSame('Buyer Co', $xpath->evaluate('string(/invoice:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName)'));
        $this->assertSame('0200000', $xpath->evaluate('string(/invoice:Invoice/cbc:InvoiceTypeCode/@name)'));
        $this->assertSame('23', $xpath->evaluate('string(/invoice:Invoice/cac:AdditionalDocumentReference[cbc:ID="ICV"]/cbc:UUID)'));
        $this->assertSame(self::VALID_PIH, $xpath->evaluate('string(/invoice:Invoice/cac:AdditionalDocumentReference[cbc:ID="PIH"]/cac:Attachment/cbc:EmbeddedDocumentBinaryObject)'));
        $this->assertSame('urn:oasis:names:specification:ubl:signature:Invoice', $xpath->evaluate('string(/invoice:Invoice/cac:Signature/cbc:ID)'));
        $this->assertSame('2322', $xpath->evaluate('string(/invoice:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:BuildingNumber)'));
        $this->assertSame('1234', $xpath->evaluate('string(/invoice:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:PlotIdentification)'));
        $this->assertSame('Al-Murabba', $xpath->evaluate('string(/invoice:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:CitySubdivisionName)'));
        $this->assertSame('1010010000', $xpath->evaluate('string(/invoice:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID)'));
        $this->assertSame('10', $xpath->evaluate('string(/invoice:Invoice/cac:PaymentMeans/cbc:PaymentMeansCode)'));
        $this->assertSame('discount', $xpath->evaluate('string(/invoice:Invoice/cac:AllowanceCharge/cbc:AllowanceChargeReason)'));
        $this->assertSame('0.00', $xpath->evaluate('string(/invoice:Invoice/cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount)'));
        $this->assertSame('0.00', $xpath->evaluate('string(/invoice:Invoice/cac:LegalMonetaryTotal/cbc:PrepaidAmount)'));
        $this->assertSame('230.00', $xpath->evaluate('string(/invoice:Invoice/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount)'));
        $this->assertSame('30.00', $xpath->evaluate('string(/invoice:Invoice/cac:TaxTotal/cbc:TaxAmount)'));
        $this->assertSame('2', $xpath->evaluate('string(count(/invoice:Invoice/cac:TaxTotal))'));
        $this->assertSame('1', $xpath->evaluate('string(count(/invoice:Invoice/cac:InvoiceLine))'));
    }
}
