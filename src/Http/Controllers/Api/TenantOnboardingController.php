<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Maaz\LaravelZatca\Http\Requests\StoreTenantOnboardingRequest;
use Maaz\LaravelZatca\Http\Requests\UpdateTenantOnboardingRequest;
use Maaz\LaravelZatca\Http\Resources\TenantOnboardingResource;
use Maaz\LaravelZatca\Tenancy\Access\TenantAccessManager;
use Maaz\LaravelZatca\Tenancy\Onboarding\TenantOnboardingFlow;

class TenantOnboardingController extends Controller
{
    public function __construct(
        protected TenantOnboardingFlow $flow,
        protected TenantAccessManager $access
    ) {
    }

    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return TenantOnboardingResource::collection(
            $this->access->scopeVisibleTenants($this->flow->listTenants())
        );
    }

    public function store(StoreTenantOnboardingRequest $request): JsonResponse
    {
        abort_unless($this->access->canManageTenants(), 403);

        $tenant = $this->flow->createTenant($request->validated());

        return (new TenantOnboardingResource($tenant))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $tenant): TenantOnboardingResource
    {
        $this->access->authorizeTenantAccess($tenant);

        return new TenantOnboardingResource($this->flow->findTenantOrFail($tenant));
    }

    public function update(UpdateTenantOnboardingRequest $request, string $tenant): TenantOnboardingResource
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->flow->findTenantOrFail($tenant);

        return new TenantOnboardingResource(
            $this->flow->updateTenant($tenantModel, $request->validated())
        );
    }
}
