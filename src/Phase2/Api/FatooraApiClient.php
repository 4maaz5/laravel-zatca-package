<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase2\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use InvalidArgumentException;
use Maaz\LaravelZatca\Contracts\ApiClient;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\ApiException;
use Maaz\LaravelZatca\Support\ZatcaLogger;

class FatooraApiClient implements ApiClient
{
    public function __construct(
        protected Factory $http,
        protected ZatcaLogger $logger
    ) {
    }

    public function submit(array $payload, TenantConfig $tenantConfig, string $mode): array
    {
        [$username, $password] = $this->resolveCredentials($tenantConfig);
        $url = $this->resolveEndpoint($tenantConfig, $mode);

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->withBasicAuth($username, $password)
                ->withHeaders($this->submissionHeaders($tenantConfig, $mode))
                ->timeout((int) ($tenantConfig->api['timeout'] ?? 30))
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            $this->logger->error((string) trans('zatca::messages.log_error'), [
                'stage' => 'api_connection',
                'mode' => $mode,
                'tenant_id' => $tenantConfig->tenantId,
                'url' => $url,
            ], $exception);
            throw new ApiException((string) trans('zatca::exceptions.api_connection_failed'), previous: $exception);
        }

        if (in_array($response->status(), [401, 403], true)) {
            $this->logger->error((string) trans('zatca::messages.log_error'), [
                'stage' => 'api_auth',
                'mode' => $mode,
                'tenant_id' => $tenantConfig->tenantId,
                'status_code' => $response->status(),
            ]);
            throw new ApiException((string) trans('zatca::exceptions.api_auth_failed'));
        }

        if ($response->serverError()) {
            $this->logger->error((string) trans('zatca::messages.log_error'), [
                'stage' => 'api_server',
                'mode' => $mode,
                'tenant_id' => $tenantConfig->tenantId,
                'status_code' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
            throw new ApiException((string) trans('zatca::exceptions.api_server_error', ['status' => $response->status()]));
        }

        $this->logger->debug((string) trans('zatca::messages.log_api_response'), [
            'mode' => $mode,
            'tenant_id' => $tenantConfig->tenantId,
            'status_code' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]);

        return [
            'success' => $response->successful(),
            'mode' => $mode,
            'status_code' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveCredentials(TenantConfig $tenantConfig): array
    {
        $username = (string) (
            $tenantConfig->api['client_id']
            ?? $tenantConfig->api['binary_security_token']
            ?? $tenantConfig->api['username']
            ?? ''
        );

        $password = (string) (
            $tenantConfig->api['client_secret']
            ?? $tenantConfig->api['secret']
            ?? $tenantConfig->api['password']
            ?? ''
        );

        if ($username === '' || $password === '') {
            $this->logger->error((string) trans('zatca::messages.log_error'), [
                'stage' => 'api_credentials',
                'tenant_id' => $tenantConfig->tenantId,
            ]);
            throw new ApiException((string) trans('zatca::exceptions.api_missing_credentials'));
        }

        return [$username, $password];
    }

    protected function resolveEndpoint(TenantConfig $tenantConfig, string $mode): string
    {
        $baseUrl = $this->resolveBaseUrl($tenantConfig);

        return match ($mode) {
            'clearance' => (string) (
                $tenantConfig->api['clearance_url']
                ?? ($baseUrl !== '' ? $baseUrl . '/invoices/clearance/single' : '')
            ),
            'reporting' => (string) (
                $tenantConfig->api['reporting_url']
                ?? ($baseUrl !== '' ? $baseUrl . '/invoices/reporting/single' : '')
            ),
            default => throw new InvalidArgumentException(sprintf('Unsupported API submission mode: %s', $mode)),
        };
    }

    protected function resolveBaseUrl(TenantConfig $tenantConfig): string
    {
        $configured = rtrim((string) ($tenantConfig->api['base_url'] ?? ''), '/');

        if ($configured !== '') {
            return $configured;
        }

        return match ($tenantConfig->environment) {
            'production' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
            'simulation' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
            default => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
        };
    }

    /**
     * ZATCA's reporting and clearance APIs require these headers in the
     * developer portal sandbox and production-like flows.
     *
     * @return array<string, string>
     */
    protected function submissionHeaders(TenantConfig $tenantConfig, string $mode): array
    {
        return [
            'Accept-Version' => (string) ($tenantConfig->api['accept_version'] ?? 'V2'),
            'accept-language' => $this->acceptLanguage($tenantConfig),
            'Clearance-Status' => $this->clearanceStatus($tenantConfig, $mode),
        ];
    }

    protected function acceptLanguage(TenantConfig $tenantConfig): string
    {
        $language = (string) ($tenantConfig->api['accept_language'] ?? $tenantConfig->language);

        return in_array($language, ['en', 'ar'], true) ? $language : 'en';
    }

    protected function clearanceStatus(TenantConfig $tenantConfig, string $mode): string
    {
        $configured = $tenantConfig->api['clearance_status'] ?? null;

        if (is_array($configured) && isset($configured[$mode])) {
            return (string) $configured[$mode];
        }

        if (is_scalar($configured) && $configured !== '') {
            return (string) $configured;
        }

        return $mode === 'clearance' ? '1' : '0';
    }
}
