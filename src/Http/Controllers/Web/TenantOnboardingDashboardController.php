<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Maaz\LaravelZatca\Http\Resources\TenantInvoiceResource;
use Maaz\LaravelZatca\Http\Resources\TenantOnboardingResource;
use Maaz\LaravelZatca\Tenancy\Access\TenantAccessManager;
use Maaz\LaravelZatca\Tenancy\Invoices\TenantInvoiceSubmissionFlow;
use Maaz\LaravelZatca\Tenancy\Onboarding\TenantOnboardingFlow;
use Maaz\LaravelZatca\Tenancy\SimpleWorkspaceManager;

class TenantOnboardingDashboardController extends Controller
{
    public function __construct(
        protected TenantOnboardingFlow $flow,
        protected TenantInvoiceSubmissionFlow $invoiceFlow,
        protected TenantAccessManager $access,
        protected SimpleWorkspaceManager $simpleWorkspace
    ) {
    }

    public function __invoke(Request $request, ?string $tenant = null)
    {
        $locale = $this->resolveLocale($request);
        App::setLocale($locale);

        $tenantModels = $this->access->scopeVisibleTenants($this->flow->listTenants());
        $tenants = TenantOnboardingResource::collection($tenantModels)
            ->response()
            ->getData(true)['data'] ?? [];
        $preferredTenant = $tenant ?? $this->access->preferredTenantKey() ?? ($tenants[0]['key'] ?? null);
        $selectedTenantModel = null;

        if (is_string($preferredTenant) && $preferredTenant !== '') {
            $this->access->authorizeTenantAccess($preferredTenant);
            $selectedTenantModel = $this->flow->findTenantOrFail($preferredTenant);
        }

        $selectedTenant = $selectedTenantModel !== null
            ? (new TenantOnboardingResource($selectedTenantModel))->resolve()
            : null;
        $selectedInvoices = $selectedTenantModel !== null
            ? TenantInvoiceResource::collection($this->invoiceFlow->listInvoices($selectedTenantModel))->resolve()
            : [];

        $simpleMode = $this->simpleWorkspace->enabled();
        $canManageTenants = $simpleMode ? false : $this->access->canManageTenants();
        $showTenantSwitcher = $canManageTenants
            || (bool) config('zatca.onboarding.dashboard.show_tenant_switcher_for_tenant_users', false);

        return view('zatca::onboarding.dashboard', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'tenants' => $tenants,
            'selectedTenant' => $selectedTenant,
            'selectedInvoices' => $selectedInvoices,
            'canManageTenants' => $canManageTenants,
            'showTenantSwitcher' => $simpleMode ? false : $showTenantSwitcher,
            'simpleMode' => $simpleMode,
            'showNotificationHooks' => $simpleMode ? $this->simpleWorkspace->showNotificationHooks() : true,
            'apiPrefix' => '/' . ltrim((string) config('zatca.onboarding.api.prefix', 'api/zatca/onboarding'), '/'),
            'dashboardPrefix' => '/' . ltrim((string) config('zatca.onboarding.dashboard.prefix', 'zatca/onboarding/dashboard'), '/'),
        ]);
    }

    private function resolveLocale(Request $request): string
    {
        $locale = strtolower((string) $request->query('lang', App::getLocale()));

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }
}
