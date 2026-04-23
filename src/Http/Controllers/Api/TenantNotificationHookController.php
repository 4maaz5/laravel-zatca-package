<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Maaz\LaravelZatca\Http\Requests\StoreTenantNotificationHookRequest;
use Maaz\LaravelZatca\Http\Requests\UpdateTenantNotificationHookRequest;
use Maaz\LaravelZatca\Http\Resources\TenantNotificationHookResource;
use Maaz\LaravelZatca\Http\Resources\TenantOnboardingResource;
use Maaz\LaravelZatca\Tenancy\Access\TenantAccessManager;
use Maaz\LaravelZatca\Tenancy\Onboarding\TenantOnboardingFlow;

class TenantNotificationHookController extends Controller
{
    public function __construct(
        protected TenantOnboardingFlow $flow,
        protected TenantAccessManager $access
    ) {
    }

    public function index(string $tenant)
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->flow->findTenantOrFail($tenant);

        return TenantNotificationHookResource::collection($tenantModel->notificationHooks()->latest()->get());
    }

    public function store(StoreTenantNotificationHookRequest $request, string $tenant): JsonResponse
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->flow->findTenantOrFail($tenant);

        $hook = $tenantModel->notificationHooks()->create([
            'name' => $request->validated('name'),
            'channel' => $request->validated('channel', 'webhook'),
            'target_url' => $request->validated('target_url'),
            'events' => $request->validated('events', ['health_alert']),
            'is_active' => $request->validated('is_active', true),
            'secret' => $request->validated('secret'),
            'metadata' => $request->validated('metadata', []),
        ]);

        return response()->json([
            'message' => 'Notification hook saved successfully.',
            'tenant' => (new TenantOnboardingResource($tenantModel->fresh(['credentials', 'invoiceStates', 'notificationHooks'])))->resolve(),
            'hook' => (new TenantNotificationHookResource($hook))->resolve(),
        ]);
    }

    public function update(UpdateTenantNotificationHookRequest $request, string $tenant, string $hook): JsonResponse
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->flow->findTenantOrFail($tenant);
        $hookModel = $tenantModel->notificationHooks()->findOrFail($hook);

        $hookModel->fill($request->validated());
        $hookModel->save();

        return response()->json([
            'message' => 'Notification hook updated successfully.',
            'tenant' => (new TenantOnboardingResource($tenantModel->fresh(['credentials', 'invoiceStates', 'notificationHooks'])))->resolve(),
            'hook' => (new TenantNotificationHookResource($hookModel))->resolve(),
        ]);
    }

    public function destroy(string $tenant, string $hook): JsonResponse
    {
        $this->access->authorizeTenantAccess($tenant);
        $tenantModel = $this->flow->findTenantOrFail($tenant);
        $hookModel = $tenantModel->notificationHooks()->findOrFail($hook);
        $hookModel->delete();

        return response()->json([
            'message' => 'Notification hook removed successfully.',
            'tenant' => (new TenantOnboardingResource($tenantModel->fresh(['credentials', 'invoiceStates', 'notificationHooks'])))->resolve(),
        ]);
    }
}
