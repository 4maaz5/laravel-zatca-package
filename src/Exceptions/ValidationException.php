<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Exceptions;

final class ValidationException extends ZatcaException
{
    /**
     * @param array<int, string> $errors
     */
    public function __construct(
        public readonly array $errors
    ) {
        parent::__construct((string) trans('zatca::exceptions.validation_failed'));
    }
}
