<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Exceptions;

class TenantNotResolvedException extends ZatcaException
{
    public function __construct()
    {
        parent::__construct('No tenant context could be resolved.');
    }
}
