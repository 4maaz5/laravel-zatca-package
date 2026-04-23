<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Support;

use Maaz\LaravelZatca\Contracts\HashGenerator;

class Sha256HashGenerator implements HashGenerator
{
    public function generate(string $payload): string
    {
        return base64_encode(hash('sha256', $payload, true));
    }
}
