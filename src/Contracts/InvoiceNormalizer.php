<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\InvoiceData;

interface InvoiceNormalizer
{
    public function normalize(array|InvoiceData $invoice): InvoiceData;
}
