<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maaz\LaravelZatca\Http\Controllers\Web\TenantOnboardingDashboardController;

Route::middleware(array_values(array_unique(array_merge(
    (array) config('zatca.onboarding.dashboard.middleware', ['web']),
    (bool) config('zatca.onboarding.dashboard.require_auth', false)
        ? (array) config('zatca.onboarding.dashboard.auth_middleware', ['auth'])
        : []
))))
    ->prefix((string) config('zatca.onboarding.dashboard.prefix', 'zatca/onboarding/dashboard'))
    ->group(function (): void {
        Route::get('/', TenantOnboardingDashboardController::class)->name('zatca.onboarding.dashboard');
        Route::get('/{tenant}', TenantOnboardingDashboardController::class)->name('zatca.onboarding.dashboard.tenant');
    });
