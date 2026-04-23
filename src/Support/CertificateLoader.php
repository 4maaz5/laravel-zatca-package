<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Support;

use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\CertificateException;
use OpenSSLAsymmetricKey;

class CertificateLoader
{
    /**
     * @return array<string, mixed>|null
     */
    public function inspectCertificate(string $certificate): ?array
    {
        $normalizedCertificate = $this->normalizeCertificate($certificate);

        if ($normalizedCertificate === null) {
            return null;
        }

        $parsed = @openssl_x509_parse($normalizedCertificate);

        if (! is_array($parsed)) {
            return null;
        }

        return [
            'normalized' => $normalizedCertificate,
            'parsed' => $parsed,
            'vat_number' => $this->extractVatNumber($normalizedCertificate),
            'valid_from' => isset($parsed['validFrom_time_t']) ? (int) $parsed['validFrom_time_t'] : null,
            'valid_to' => isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t'] : null,
            'subject' => is_array($parsed['subject'] ?? null) ? $parsed['subject'] : [],
            'issuer' => is_array($parsed['issuer'] ?? null) ? $parsed['issuer'] : [],
            'serial_number' => $parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? null,
        ];
    }

    public function normalizeCertificateString(string $certificate): ?string
    {
        return $this->normalizeCertificate($certificate);
    }

    public function extractVatNumber(string $certificate): ?string
    {
        $normalizedCertificate = $this->normalizeCertificate($certificate);

        if ($normalizedCertificate === null) {
            return null;
        }

        $parsed = @openssl_x509_parse($normalizedCertificate);

        if (! is_array($parsed)) {
            return null;
        }

        $subjectAlternativeName = (string) ($parsed['extensions']['subjectAltName'] ?? '');

        if ($subjectAlternativeName !== '') {
            if (preg_match('/OID\.0\.9\.2342\.19200300\.100\.1\.1\s*=\s*([0-9]+)/', $subjectAlternativeName, $matches) === 1) {
                return $matches[1];
            }

            if (preg_match('/UID\s*=\s*([0-9]+)/', $subjectAlternativeName, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    public function loadPrivateKey(TenantConfig $tenantConfig): OpenSSLAsymmetricKey
    {
        $privateKey = $this->resolveValue(
            $tenantConfig->certificates['private_key'] ?? null,
            $tenantConfig->certificates['private_key_path'] ?? null,
            'private key'
        );

        $passphrase = $tenantConfig->certificates['secret'] ?? null;
        $resource = $this->loadPrivateKeyResource($privateKey, is_string($passphrase) ? $passphrase : '');

        if ($resource === false) {
            throw new CertificateException((string) trans('zatca::exceptions.certificate_invalid_private_key'));
        }

        return $resource;
    }

    public function loadCertificate(TenantConfig $tenantConfig): ?string
    {
        $certificate = $this->resolveOptionalValue(
            $tenantConfig->certificates['certificate'] ?? null,
            $tenantConfig->certificates['certificate_path'] ?? null
        );

        if ($certificate === null) {
            return null;
        }

        $normalizedCertificate = $this->normalizeCertificate($certificate);

        if ($normalizedCertificate === null) {
            throw new CertificateException((string) trans('zatca::exceptions.certificate_invalid_certificate'));
        }

        return $normalizedCertificate;
    }

    protected function resolveValue(mixed $inline, mixed $path, string $label): string
    {
        $value = $this->resolveOptionalValue($inline, $path);

        if ($value === null || $value === '') {
            throw new CertificateException((string) trans('zatca::exceptions.certificate_missing_private_key'));
        }

        return $value;
    }

    protected function resolveOptionalValue(mixed $inline, mixed $path): ?string
    {
        if (is_string($inline) && trim($inline) !== '') {
            return $inline;
        }

        if (is_string($path) && trim($path) !== '') {
            if (! is_file($path) || ! is_readable($path)) {
                throw new CertificateException((string) trans('zatca::exceptions.certificate_unreadable_path', ['path' => $path]));
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new CertificateException((string) trans('zatca::exceptions.certificate_unreadable_file', ['path' => $path]));
            }

            return $contents;
        }

        return null;
    }

    protected function normalizeCertificate(string $certificate): ?string
    {
        $certificate = trim($certificate);

        if ($certificate === '') {
            return null;
        }

        if (@openssl_x509_read($certificate) !== false) {
            return $certificate;
        }

        $decoded = base64_decode($certificate, true);

        if (is_string($decoded) && str_contains($decoded, 'BEGIN CERTIFICATE') && @openssl_x509_read($decoded) !== false) {
            return $decoded;
        }

        if (is_string($decoded) && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $decoded) === 1) {
            $doubleDecoded = base64_decode(preg_replace('/\s+/', '', $decoded) ?? '', true);

            if (is_string($doubleDecoded)) {
                $pemFromDoubleDecoded = $this->toPem(base64_encode($doubleDecoded));

                if (@openssl_x509_read($pemFromDoubleDecoded) !== false) {
                    return $pemFromDoubleDecoded;
                }
            }
        }

        $pemFromToken = $this->toPem($certificate);

        if (@openssl_x509_read($pemFromToken) !== false) {
            return $pemFromToken;
        }

        if (is_string($decoded)) {
            $pemFromDecoded = $this->toPem(base64_encode($decoded));

            if (@openssl_x509_read($pemFromDecoded) !== false) {
                return $pemFromDecoded;
            }
        }

        return null;
    }

    protected function loadPrivateKeyResource(string $privateKey, string $passphrase): OpenSSLAsymmetricKey|false
    {
        $privateKey = trim($privateKey);
        $candidates = [$privateKey];
        $decoded = base64_decode($privateKey, true);

        if (is_string($decoded) && str_contains($decoded, 'PRIVATE KEY')) {
            $candidates[] = $decoded;
        }

        if (! str_contains($privateKey, 'PRIVATE KEY')) {
            $candidates[] = $this->toPemBlock('EC PRIVATE KEY', $privateKey);
            $candidates[] = $this->toPemBlock('PRIVATE KEY', $privateKey);
        }

        if (is_string($decoded) && ! str_contains($decoded, 'PRIVATE KEY')) {
            $encodedDecoded = base64_encode($decoded);
            $candidates[] = $this->toPemBlock('EC PRIVATE KEY', $encodedDecoded);
            $candidates[] = $this->toPemBlock('PRIVATE KEY', $encodedDecoded);
        }

        foreach (array_unique($candidates) as $candidate) {
            $resource = openssl_pkey_get_private($candidate, $passphrase);

            if ($resource instanceof OpenSSLAsymmetricKey) {
                return $resource;
            }
        }

        return false;
    }

    protected function toPem(string $base64Certificate): string
    {
        return $this->toPemBlock('CERTIFICATE', $base64Certificate);
    }

    protected function toPemBlock(string $label, string $base64Contents): string
    {
        return "-----BEGIN {$label}-----\n"
            . chunk_split(preg_replace('/\s+/', '', $base64Contents) ?? '', 64, "\n")
            . "-----END {$label}-----\n";
    }
}
