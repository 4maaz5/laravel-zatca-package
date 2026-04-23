<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Maaz\LaravelZatca\Http\Requests\GenerateTenantCsrRequest;
use Maaz\LaravelZatca\Http\Requests\IssueComplianceCsidRequest;
use Maaz\LaravelZatca\Http\Requests\IssueProductionCsidRequest;
use Maaz\LaravelZatca\Http\Resources\TenantOnboardingResource;
use Maaz\LaravelZatca\Tenancy\Access\TenantAccessManager;
use Maaz\LaravelZatca\Tenancy\Onboarding\TenantOnboardingFlow;

class TenantOnboardingActionController extends Controller
{
    public function __construct(
        protected TenantOnboardingFlow $flow,
        protected TenantAccessManager $access
    ) {
    }

    public function generateCsr(GenerateTenantCsrRequest $request, string $tenant): JsonResponse
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->flow->findTenantOrFail($tenant);
        $result = $this->flow->generateCsr($tenantModel, $request->validated());

        return response()->json([
            'message' => 'CSR generated successfully.',
            'tenant' => (new TenantOnboardingResource($tenantModel->fresh(['credentials', 'invoiceStates'])))->resolve(),
            'csr' => [
                'base64' => $result->csrBase64,
                'pem' => $result->csrPem,
                'properties' => $result->properties,
                'simulation' => $result->simulation,
                'non_production' => $result->nonProduction,
            ],
        ]);
    }

    public function issueComplianceCsid(IssueComplianceCsidRequest $request, string $tenant): JsonResponse
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->flow->findTenantOrFail($tenant);
        $result = $this->flow->issueComplianceCsid($tenantModel, $request->validated());

        return response()->json([
            'message' => 'Compliance CSID issued successfully.',
            'tenant' => (new TenantOnboardingResource($tenantModel->fresh(['credentials', 'invoiceStates'])))->resolve(),
            'compliance_csid' => $result['body'] ?? $result,
        ]);
    }

    public function issueProductionCsid(IssueProductionCsidRequest $request, string $tenant): JsonResponse
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->flow->findTenantOrFail($tenant);
        $result = $this->flow->issueProductionCsid($tenantModel, $request->validated());

        return response()->json([
            'message' => 'Production CSID issued successfully.',
            'tenant' => (new TenantOnboardingResource($tenantModel->fresh(['credentials', 'invoiceStates'])))->resolve(),
            'production_csid' => $result['body'] ?? $result,
        ]);
    }
}
