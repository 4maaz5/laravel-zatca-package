<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Exceptions;

use RuntimeException;

class ZatcaException extends RuntimeException
{
    public static function notImplemented(string $feature): self
    {
        return new self((string) trans('zatca::exceptions.not_implemented', ['feature' => $feature]));
    }
}
