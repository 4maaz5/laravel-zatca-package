<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Phase2\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Maaz\LaravelZatca\Contracts\OnboardingClient;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\Exceptions\ApiException;
use Maaz\LaravelZatca\Support\ZatcaLogger;

class FatooraOnboardingClient implements OnboardingClient
{
    public function __construct(
        protected Factory $http,
        protected ZatcaLogger $logger
    ) {
    }

    public function requestComplianceCsid(array $payload, TenantConfig $tenantConfig): array
    {
        $url = $this->resolveRequiredEndpoint($tenantConfig, 'compliance_csid_url');
        $headers = [
            'Accept-Version' => (string) ($tenantConfig->api['accept_version'] ?? 'V2'),
        ];

        if (isset($payload['otp'])) {
            $headers['OTP'] = (string) $payload['otp'];
        }

        return $this->postJson($url, [
            'csr' => $payload['csr'] ?? null,
        ], $headers, $tenantConfig, 'compliance_csid');
    }

    public function requestProductionCsid(array $payload, TenantConfig $tenantConfig): array
    {
        $url = $this->resolveRequiredEndpoint($tenantConfig, 'production_csid_url');
        [$username, $password] = $this->resolveCredentials(
            $payload['binary_security_token'] ?? null,
            $payload['secret'] ?? null,
            $tenantConfig
        );

        return $this->postJson(
            $url,
            ['compliance_request_id' => $payload['compliance_request_id'] ?? $payload['request_id'] ?? null],
            ['Accept-Version' => (string) ($tenantConfig->api['accept_version'] ?? 'V2')],
            $tenantConfig,
            'production_csid',
            $username,
            $password
        );
    }

    public function complianceCheck(array $payload, TenantConfig $tenantConfig): array
    {
        $url = $this->resolveRequiredEndpoint($tenantConfig, 'compliance_checks_url');
        [$username, $password] = $this->resolveCredentials(
            $payload['binary_security_token'] ?? null,
            $payload['secret'] ?? null,
            $tenantConfig
        );

        return $this->postJson(
            $url,
            [
                'uuid' => $payload['uuid'] ?? null,
                'invoiceHash' => $payload['invoiceHash'] ?? $payload['invoice_hash'] ?? null,
                'invoice' => $payload['invoice'] ?? null,
            ],
            [
                'Accept-Version' => (string) ($tenantConfig->api['accept_version'] ?? 'V2'),
                'Accept-Language' => $this->acceptLanguage($tenantConfig),
            ],
            $tenantConfig,
            'compliance_checks',
            $username,
            $password
        );
    }

    protected function postJson(
        string $url,
        array $payload,
        array $headers,
        TenantConfig $tenantConfig,
        string $stage,
        ?string $username = null,
        ?string $password = null
    ): array {
        try {
            $request = $this->http
                ->acceptJson()
                ->asJson()
                ->withHeaders($headers)
                ->timeout((int) ($tenantConfig->api['timeout'] ?? 30));

            if ($username !== null && $password !== null) {
                $request = $request->withBasicAuth($username, $password);
            }

            $response = $request->post($url, $payload);
        } catch (ConnectionException $exception) {
            $this->logger->error((string) trans('zatca::messages.log_error'), [
                'stage' => $stage,
                'tenant_id' => $tenantConfig->tenantId,
                'url' => $url,
            ], $exception);

            throw new ApiException((string) trans('zatca::exceptions.api_connection_failed'), previous: $exception);
        }

        if ($response->failed() && $response->serverError()) {
            throw new ApiException((string) trans('zatca::exceptions.api_server_error', ['status' => $response->status()]));
        }

        return [
            'success' => $response->successful(),
            'status_code' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json() ?? $response->body(),
            'stage' => $stage,
        ];
    }

    protected function resolveRequiredEndpoint(TenantConfig $tenantConfig, string $key): string
    {
        $url = (string) ($tenantConfig->api[$key] ?? '');

        if ($url !== '') {
            return $url;
        }

        $baseUrl = $this->resolveBaseUrl($tenantConfig);
        $path = match ($key) {
            'compliance_csid_url' => '/compliance',
            'production_csid_url' => '/production/csids',
            'compliance_checks_url' => '/compliance/invoices',
            default => '',
        };

        if ($baseUrl === '' || $path === '') {
            throw new ApiException(sprintf('Missing onboarding endpoint configuration: %s', $key));
        }

        return $baseUrl . $path;
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
     * @return array{0: string, 1: string}
     */
    protected function resolveCredentials(?string $username, ?string $password, TenantConfig $tenantConfig): array
    {
        $resolvedUsername = $username ?: (string) ($tenantConfig->api['binary_security_token'] ?? $tenantConfig->api['client_id'] ?? '');
        $resolvedPassword = $password ?: (string) ($tenantConfig->api['secret'] ?? $tenantConfig->api['client_secret'] ?? '');

        if ($resolvedUsername === '' || $resolvedPassword === '') {
            throw new ApiException((string) trans('zatca::exceptions.api_missing_credentials'));
        }

        return [$resolvedUsername, $resolvedPassword];
    }

    protected function acceptLanguage(TenantConfig $tenantConfig): string
    {
        $language = (string) ($tenantConfig->api['accept_language'] ?? $tenantConfig->language);

        return in_array($language, ['en', 'ar'], true) ? $language : 'en';
    }
}
