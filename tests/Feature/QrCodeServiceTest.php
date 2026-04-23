<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    public function test_it_generates_a_zatca_compliant_qr_payload(): void
    {
        $invoice = Zatca::invoice()
            ->invoiceNumber('INV-1002')
            ->issuedAt('2026-04-13T10:30:00+03:00')
            ->seller([
                'name' => 'Maaz Store',
                'vat_number' => '300000000000003',
            ])
            ->addItem([
                'name' => 'Product A',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_percent' => 15,
            ])
            ->generate();

        $qr = Zatca::generateQr($invoice);
        $decoded = base64_decode($qr, true);

        $this->assertNotFalse($decoded);

        $fields = $this->decodeTlv($decoded);

        $this->assertSame('Maaz Store', $fields[1]);
        $this->assertSame('300000000000003', $fields[2]);
        $this->assertSame('2026-04-13T10:30:00+03:00', $fields[3]);
        $this->assertSame('115.00', $fields[4]);
        $this->assertSame('15.00', $fields[5]);
    }

    /**
     * @return array<int, string>
     */
    private function decodeTlv(string $payload): array
    {
        $fields = [];
        $offset = 0;
        $length = strlen($payload);

        while ($offset < $length) {
            $tag = ord($payload[$offset]);
            $valueLength = ord($payload[$offset + 1]);
            $value = substr($payload, $offset + 2, $valueLength);
            $fields[$tag] = $value;
            $offset += 2 + $valueLength;
        }

        return $fields;
    }
}
