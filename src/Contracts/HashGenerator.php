<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Contracts;

interface HashGenerator
{
    public function generate(string $payload): string;
}
