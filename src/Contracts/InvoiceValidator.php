<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

use Maaz\LaravelZatca\DTOs\InvoiceData;

interface InvoiceValidator
{
    public function validate(InvoiceData $invoice): array;
}
