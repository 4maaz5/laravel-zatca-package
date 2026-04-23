<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase1\Encoders;

final class TlvEncoder
{
    /**
     * @param array<int, string> $fields
     */
    public function encode(array $fields): string
    {
        $payload = '';

        foreach ($fields as $tag => $value) {
            $payload .= $this->encodeField($tag, $value);
        }

        return $payload;
    }

    public function encodeField(int $tag, string $value): string
    {
        $encodedValue = (string) $value;
        $length = strlen($encodedValue);

        return chr($tag) . chr($length) . $encodedValue;
    }
}
