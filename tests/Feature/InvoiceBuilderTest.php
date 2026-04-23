<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\Tests\TestCase;

class InvoiceBuilderTest extends TestCase
{
    public function test_it_builds_an_invoice_and_calculates_totals(): void
    {
        $invoice = Zatca::invoice()
            ->invoiceNumber('INV-1001')
            ->seller([
                'name' => 'Maaz Store',
                'vat_number' => '300000000000003',
            ])
            ->buyer([
                'name' => 'Customer One',
                'vat_number' => '300000000000013',
            ])
            ->addItem([
                'name' => 'Product A',
                'quantity' => 2,
                'unit_price' => 100,
                'tax_percent' => 15,
            ])
            ->addItem([
                'name' => 'Product B',
                'quantity' => 1,
                'unit_price' => 50,
                'tax_percent' => 15,
            ])
            ->generate();

        $this->assertInstanceOf(InvoiceData::class, $invoice);
        $this->assertSame('INV-1001', $invoice->invoiceNumber);
        $this->assertSame('Maaz Store', $invoice->seller->name);
        $this->assertSame('Customer One', $invoice->buyer?->name);
        $this->assertCount(2, $invoice->items);
        $this->assertSame(250.00, $invoice->subtotal);
        $this->assertSame(37.50, $invoice->taxAmount);
        $this->assertSame(287.50, $invoice->totalAmount);
        $this->assertNotSame('', $invoice->uuid);
        $this->assertNotSame('', $invoice->issuedAt);
    }
}
