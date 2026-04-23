<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Maaz\LaravelZatca\Exceptions\ZatcaException;
use Maaz\LaravelZatca\Http\Requests\SubmitTenantInvoiceRequest;
use Maaz\LaravelZatca\Http\Resources\TenantInvoiceResource;
use Maaz\LaravelZatca\Http\Resources\TenantOnboardingResource;
use Maaz\LaravelZatca\Tenancy\Access\TenantAccessManager;
use Maaz\LaravelZatca\Tenancy\Invoices\TenantInvoiceSubmissionFlow;
use Maaz\LaravelZatca\Tenancy\Models\ZatcaTenantInvoice;
use Maaz\LaravelZatca\Tenancy\Onboarding\TenantOnboardingFlow;

class TenantInvoiceController extends Controller
{
    public function __construct(
        protected TenantOnboardingFlow $onboardingFlow,
        protected TenantInvoiceSubmissionFlow $invoiceFlow,
        protected TenantAccessManager $access
    ) {
    }

    public function index(Request $request, string $tenant): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->onboardingFlow->findTenantOrFail($tenant);

        return TenantInvoiceResource::collection(
            $this->invoiceFlow->paginateInvoices(
                $tenantModel,
                $request->only(['search', 'mode', 'status', 'date_from', 'date_to']),
                max(1, min((int) $request->integer('per_page', 10), 50))
            )
        );
    }

    public function store(SubmitTenantInvoiceRequest $request, string $tenant): JsonResponse
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->onboardingFlow->findTenantOrFail($tenant);

        try {
            $invoice = $this->invoiceFlow->submitInvoice($tenantModel, $request->validated());
        } catch (ZatcaException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => class_basename($exception),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Invoice submitted successfully.',
            'tenant' => (new TenantOnboardingResource($tenantModel->fresh(['credentials', 'invoiceStates', 'notificationHooks'])))->resolve(),
            'invoice' => (new TenantInvoiceResource($invoice))->resolve(),
            'invoices' => TenantInvoiceResource::collection($this->invoiceFlow->listInvoices($tenantModel))->resolve(),
        ]);
    }

    public function show(string $tenant, string $invoice): JsonResponse
    {
        $this->access->authorizeTenantAccess($tenant);

        return response()->json([
            'data' => $this->detailPayload($this->resolveInvoice($tenant, $invoice)),
        ]);
    }

    public function downloadXml(string $tenant, string $invoice): Response
    {
        $this->access->authorizeTenantAccess($tenant);
        $invoiceModel = $this->resolveInvoice($tenant, $invoice);

        return response($invoiceModel->xml ?? '', 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . ($invoiceModel->invoice_number ?: 'invoice') . '-xml.xml"',
        ]);
    }

    public function downloadSignedXml(string $tenant, string $invoice): Response
    {
        $this->access->authorizeTenantAccess($tenant);
        $invoiceModel = $this->resolveInvoice($tenant, $invoice);

        return response($invoiceModel->signed_xml ?? '', 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . ($invoiceModel->invoice_number ?: 'invoice') . '-signed.xml"',
        ]);
    }

    public function downloadApiResponse(string $tenant, string $invoice): Response
    {
        $this->access->authorizeTenantAccess($tenant);
        $invoiceModel = $this->resolveInvoice($tenant, $invoice);

        return response(
            json_encode($invoiceModel->api_response ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            200,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . ($invoiceModel->invoice_number ?: 'invoice') . '-response.json"',
            ]
        );
    }

    private function resolveInvoice(string $tenant, string $invoice): ZatcaTenantInvoice
    {
        $tenantModel = $this->onboardingFlow->findTenantOrFail($tenant);

        return $tenantModel->invoices()->findOrFail($invoice);
    }

    private function detailPayload(ZatcaTenantInvoice $invoice): array
    {
        return array_merge(
            (new TenantInvoiceResource($invoice))->resolve(),
            [
                'xml' => $invoice->xml,
                'signed_xml' => $invoice->signed_xml,
                'download_urls' => [
                    'xml' => route('zatca.onboarding.tenants.invoices.download.xml', ['tenant' => $invoice->tenant?->key ?: $invoice->tenant_id, 'invoice' => $invoice->getKey()], false),
                    'signed_xml' => route('zatca.onboarding.tenants.invoices.download.signed-xml', ['tenant' => $invoice->tenant?->key ?: $invoice->tenant_id, 'invoice' => $invoice->getKey()], false),
                    'api_response' => route('zatca.onboarding.tenants.invoices.download.api-response', ['tenant' => $invoice->tenant?->key ?: $invoice->tenant_id, 'invoice' => $invoice->getKey()], false),
                ],
            ]
        );
    }
}
