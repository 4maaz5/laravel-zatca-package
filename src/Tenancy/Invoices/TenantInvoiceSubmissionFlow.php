<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tenancy\Invoices;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Maaz\LaravelZatca\DTOs\BuyerData;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\SubmissionResult;
use Maaz\LaravelZatca\Events\TenantInvoiceSubmissionAlertDetected;
use Maaz\LaravelZatca\Exceptions\ApiException;
use Maaz\LaravelZatca\Services\ZatcaManager;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenant;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantCredential;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoice;

class TenantInvoiceSubmissionFlow
{
    public function __construct(
        protected ZatcaManager $manager
    ) {
    }

    public function listInvoices(ZatcaTenant $tenant): Collection
    {
        return $tenant->invoices()
            ->latest('created_at')
            ->limit(25)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateInvoices(ZatcaTenant $tenant, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $tenant->invoices()
            ->when(! empty($filters['search']), function ($query) use ($filters): void {
                $search = '%' . trim((string) $filters['search']) . '%';
                $query->where(function ($invoiceQuery) use ($search): void {
                    $invoiceQuery->where('invoice_number', 'like', $search)
                        ->orWhere('uuid', 'like', $search)
                        ->orWhere('reporting_status', 'like', $search)
                        ->orWhere('clearance_status', 'like', $search);
                });
            })
            ->when(! empty($filters['mode']), fn ($query) => $query->where('mode', (string) $filters['mode']))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', (string) $filters['status']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('submitted_at', '>=', (string) $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('submitted_at', '<=', (string) $filters['date_to']))
            ->latest('submitted_at')
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function submitInvoice(ZatcaTenant $tenant, array $payload): ZatcaTenantInvoice
    {
        $invoiceRecord = DB::transaction(function () use ($tenant, $payload): ZatcaTenantInvoice {
            $environment = (string) ($payload['environment'] ?? $tenant->default_environment ?? 'sandbox');
            $mode = (string) ($payload['mode'] ?? 'reporting');
            $payload['environment'] = $environment;
            $payload['mode'] = $mode;
            $payload['type'] = $this->normalizeInvoiceTypeCode($payload['type'] ?? null);
            $payload['meta'] = $this->normalizeInvoiceMeta($payload['meta'] ?? [], $mode);
            $this->ensureTenantCanSubmitInvoices($tenant, $environment);
            $invoice = $this->buildInvoice($tenant, $payload);
            $manager = $this->manager->forTenant($tenant->key ?: (string) $tenant->getKey());
            $result = $mode === 'clearance'
                ? $manager->clearance($invoice)
                : $manager->report($invoice);

            return $this->persistInvoiceResult($tenant, $environment, $invoice, $result, $payload);
        });

        if ($invoiceRecord->status === 'failed') {
            Event::dispatch(new TenantInvoiceSubmissionAlertDetected($tenant, $invoiceRecord));
        }

        return $invoiceRecord;
    }

    public function invoiceSubmissionReadiness(ZatcaTenant $tenant, ?string $environment = null): array
    {
        $resolvedEnvironment = (string) ($environment ?: $tenant->default_environment ?: 'sandbox');
        $credential = $tenant->credentials->firstWhere('environment', $resolvedEnvironment);

        if (! $credential instanceof ZatcaTenantCredential) {
            return [
                'ready' => false,
                'environment' => $resolvedEnvironment,
                'code' => 'missing_environment_credentials',
                'message' => (string) trans('zatca::exceptions.invoice_submission_missing_environment_credentials', [
                    'environment' => $resolvedEnvironment,
                ]),
            ];
        }

        if (empty($credential->private_key)) {
            return [
                'ready' => false,
                'environment' => $resolvedEnvironment,
                'code' => 'missing_private_key',
                'message' => (string) trans('zatca::exceptions.invoice_submission_missing_private_key', [
                    'environment' => $resolvedEnvironment,
                ]),
            ];
        }

        if (empty($credential->compliance_binary_security_token) && empty($credential->production_binary_security_token)) {
            return [
                'ready' => false,
                'environment' => $resolvedEnvironment,
                'code' => 'missing_certificate',
                'message' => (string) trans('zatca::exceptions.invoice_submission_missing_certificate', [
                    'environment' => $resolvedEnvironment,
                ]),
            ];
        }

        return [
            'ready' => true,
            'environment' => $resolvedEnvironment,
            'code' => 'ready',
            'message' => (string) trans('zatca::onboarding.ready'),
        ];
    }

    private function buildInvoice(ZatcaTenant $tenant, array $payload): InvoiceData
    {
        $manager = $this->manager->forTenant($tenant->key ?: (string) $tenant->getKey());
        $builder = $manager->invoice()
            ->invoiceNumber((string) ($payload['invoice_number'] ?? $this->defaultInvoiceNumber($payload)))
            ->issuedAt((string) ($payload['issued_at'] ?? CarbonImmutable::now($tenant->timezone ?: 'Asia/Riyadh')->toIso8601String()))
            ->seller([
                'name' => $tenant->seller_name ?: $tenant->legal_name,
                'vat_number' => (string) $tenant->vat_number,
                'crn' => $tenant->crn,
                'street' => $tenant->street,
                'building_number' => $tenant->building_number,
                'additional_number' => $tenant->additional_number,
                'district' => $tenant->district,
                'city' => $tenant->city,
                'postal_code' => $tenant->postal_code,
                'country_code' => $tenant->country_code,
            ]);

        if (! empty($payload['buyer']['name'] ?? null)) {
            $builder->buyer($this->normalizeBuyer((array) $payload['buyer']));
        }

        foreach ((array) ($payload['items'] ?? []) as $item) {
            $builder->addItem((array) $item);
        }

        $builder->type((string) ($payload['type'] ?? '388'));

        if (! empty($payload['notes'] ?? null)) {
            $builder->notes((string) $payload['notes']);
        }

        $builder->meta(is_array($payload['meta'] ?? null) ? $payload['meta'] : []);

        return $builder->generate();
    }

    protected function normalizeInvoiceTypeCode(mixed $type): string
    {
        $raw = trim((string) $type);

        if (preg_match('/^\d{3}$/', $raw) === 1) {
            return $raw;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', $raw));

        return match ($normalized) {
            '', 'invoice', 'tax_invoice', 'standard', 'standard_invoice', 'simplified', 'simplified_invoice', 'report', 'reporting' => '388',
            'credit', 'credit_note', 'creditnote' => '381',
            'debit', 'debit_note', 'debitnote' => '383',
            default => '388',
        };
    }

    /**
     * @param  mixed  $meta
     * @return array<string, mixed>
     */
    protected function normalizeInvoiceMeta(mixed $meta, string $mode): array
    {
        $normalizedMeta = is_array($meta) ? $meta : [];
        $normalizedMeta['transaction_type_code'] = (string) ($normalizedMeta['transaction_type_code'] ?? (
            $mode === 'clearance' ? '0100000' : '0200000'
        ));

        return $normalizedMeta;
    }

    private function ensureTenantCanSubmitInvoices(ZatcaTenant $tenant, string $environment): void
    {
        $readiness = $this->invoiceSubmissionReadiness($tenant, $environment);

        if (! ($readiness['ready'] ?? false)) {
            throw new ApiException((string) ($readiness['message'] ?? trans('zatca::onboarding.submission_failed')));
        }
    }

    /**
     * @param  array<string, mixed>  $buyer
     * @return array<string, mixed>
     */
    private function normalizeBuyer(array $buyer): array
    {
        return BuyerData::fromArray($buyer)->toArray();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function defaultInvoiceNumber(array $payload): string
    {
        $prefix = strtoupper((string) ($payload['mode'] ?? 'reporting')) === 'CLEARANCE' ? 'CLR' : 'RPT';

        return $prefix . '-' . now()->format('YmdHis');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistInvoiceResult(
        ZatcaTenant $tenant,
        string $environment,
        InvoiceData $invoice,
        SubmissionResult $result,
        array $payload
    ): ZatcaTenantInvoice {
        $body = is_array($result->apiResponse['body'] ?? null) ? $result->apiResponse['body'] : [];
        $accepted = $result->accepted();

        return ZatcaTenantInvoice::query()->create([
            'tenant_id' => $tenant->getKey(),
            'environment' => $environment,
            'invoice_number' => $invoice->invoiceNumber,
            'uuid' => $invoice->uuid,
            'mode' => $result->mode,
            'invoice_type' => $invoice->type,
            'status' => $accepted ? 'submitted' : 'failed',
            'reporting_status' => Arr::get($body, 'reportingStatus'),
            'clearance_status' => Arr::get($body, 'clearanceStatus'),
            'invoice_hash' => $result->invoiceHash,
            'qr_code' => $result->qrCode,
            'seller' => $invoice->seller->toArray(),
            'buyer' => $invoice->buyer?->toArray(),
            'items' => array_map(static fn ($item): array => $item->toArray(), $invoice->items),
            'invoice_payload' => $invoice->toArray(),
            'api_response' => $result->apiResponse,
            'xml' => $result->xml,
            'signed_xml' => $result->signedXml,
            'issued_at' => CarbonImmutable::parse($invoice->issuedAt),
            'submitted_at' => now(),
            'last_error' => $accepted ? null : (string) json_encode($body, JSON_UNESCAPED_SLASHES),
            'metadata' => [
                'request_payload' => Arr::only($payload, ['environment', 'mode', 'notes', 'type', 'meta']),
            ],
        ]);
    }
}
