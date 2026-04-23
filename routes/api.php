<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maaz\LaravelZatca\Http\Controllers\Api\TenantInvoiceController;
use Maaz\LaravelZatca\Http\Controllers\Api\TenantNotificationHookController;
use Maaz\LaravelZatca\Http\Controllers\Api\TenantOnboardingActionController;
use Maaz\LaravelZatca\Http\Controllers\Api\TenantOnboardingController;

$apiMiddleware = (array) config('zatca.onboarding.api.middleware', ['api']);

if ((bool) config('zatca.onboarding.api.require_auth', false) && ! in_array('web', $apiMiddleware, true)) {
    array_unshift($apiMiddleware, 'web');
}

Route::middleware(array_values(array_unique(array_merge(
    $apiMiddleware,
    (bool) config('zatca.onboarding.api.require_auth', false)
        ? (array) config('zatca.onboarding.api.auth_middleware', ['auth'])
        : []
))))
    ->prefix((string) config('zatca.onboarding.api.prefix', 'api/zatca/onboarding'))
    ->group(function (): void {
        Route::get('/tenants', [TenantOnboardingController::class, 'index'])->name('zatca.onboarding.tenants.index');
        Route::post('/tenants', [TenantOnboardingController::class, 'store'])->name('zatca.onboarding.tenants.store');
        Route::get('/tenants/{tenant}', [TenantOnboardingController::class, 'show'])->name('zatca.onboarding.tenants.show');
        Route::match(['put', 'patch'], '/tenants/{tenant}', [TenantOnboardingController::class, 'update'])->name('zatca.onboarding.tenants.update');
        Route::post('/tenants/{tenant}/csr', [TenantOnboardingActionController::class, 'generateCsr'])->name('zatca.onboarding.tenants.csr');
        Route::post('/tenants/{tenant}/compliance-csid', [TenantOnboardingActionController::class, 'issueComplianceCsid'])->name('zatca.onboarding.tenants.compliance-csid');
        Route::post('/tenants/{tenant}/production-csid', [TenantOnboardingActionController::class, 'issueProductionCsid'])->name('zatca.onboarding.tenants.production-csid');
        Route::get('/tenants/{tenant}/notification-hooks', [TenantNotificationHookController::class, 'index'])->name('zatca.onboarding.tenants.notification-hooks.index');
        Route::post('/tenants/{tenant}/notification-hooks', [TenantNotificationHookController::class, 'store'])->name('zatca.onboarding.tenants.notification-hooks.store');
        Route::match(['put', 'patch'], '/tenants/{tenant}/notification-hooks/{hook}', [TenantNotificationHookController::class, 'update'])->name('zatca.onboarding.tenants.notification-hooks.update');
        Route::delete('/tenants/{tenant}/notification-hooks/{hook}', [TenantNotificationHookController::class, 'destroy'])->name('zatca.onboarding.tenants.notification-hooks.destroy');
        Route::get('/tenants/{tenant}/invoices', [TenantInvoiceController::class, 'index'])->name('zatca.onboarding.tenants.invoices.index');
        Route::post('/tenants/{tenant}/invoices', [TenantInvoiceController::class, 'store'])->name('zatca.onboarding.tenants.invoices.store');
        Route::get('/tenants/{tenant}/invoices/{invoice}', [TenantInvoiceController::class, 'show'])->name('zatca.onboarding.tenants.invoices.show');
        Route::get('/tenants/{tenant}/invoices/{invoice}/xml', [TenantInvoiceController::class, 'downloadXml'])->name('zatca.onboarding.tenants.invoices.download.xml');
        Route::get('/tenants/{tenant}/invoices/{invoice}/signed-xml', [TenantInvoiceController::class, 'downloadSignedXml'])->name('zatca.onboarding.tenants.invoices.download.signed-xml');
        Route::get('/tenants/{tenant}/invoices/{invoice}/api-response', [TenantInvoiceController::class, 'downloadApiResponse'])->name('zatca.onboarding.tenants.invoices.download.api-response');
    });
