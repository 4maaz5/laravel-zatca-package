<?php

declare(strict_types=1);

use Maaz\LaravelZatca\Tenancy\Repositories\ConfigTenantConfigRepository;
use Maaz\LaravelZatca\Tenancy\Repositories\DatabaseTenantConfigRepository;

return [
    'package' => [
        'name' => 'maaz/laravel-zatca',
        'locale_namespace' => 'zatca',
    ],

    'debug' => (bool) env('ZATCA_DEBUG', true),

    /*
    |--------------------------------------------------------------------------
    | Default Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | These values are used for single-tenant installations and as a fallback
    | when no tenant-specific configuration is available.
    |
    */
    'default_tenant' => [
        'tenant_id' => 'default',
        'key' => env('ZATCA_DEFAULT_TENANT_KEY', 'default'),
        'environment' => env('ZATCA_ENVIRONMENT', 'sandbox'),
        'legal_name' => env('ZATCA_LEGAL_NAME', env('ZATCA_SELLER_NAME', 'ZATCA Workspace')),
        'legal_name_ar' => env('ZATCA_LEGAL_NAME_AR'),
        'seller_name' => env('ZATCA_SELLER_NAME', ''),
        'seller_name_ar' => env('ZATCA_SELLER_NAME_AR'),
        'seller_vat_number' => env('ZATCA_SELLER_VAT_NUMBER', ''),
        'crn' => env('ZATCA_CRN'),
        'branch_name' => env('ZATCA_BRANCH_NAME'),
        'branch_name_ar' => env('ZATCA_BRANCH_NAME_AR'),
        'country_code' => env('ZATCA_COUNTRY_CODE', 'SA'),
        'city' => env('ZATCA_CITY'),
        'district' => env('ZATCA_DISTRICT'),
        'street' => env('ZATCA_STREET'),
        'building_number' => env('ZATCA_BUILDING_NUMBER'),
        'additional_number' => env('ZATCA_ADDITIONAL_NUMBER'),
        'postal_code' => env('ZATCA_POSTAL_CODE'),
        'timezone' => env('ZATCA_TIMEZONE', 'Asia/Riyadh'),
        'locale' => env('ZATCA_LOCALE', env('ZATCA_LANGUAGE', 'en')),
        'language' => env('ZATCA_LANGUAGE', 'en'),
        'certificates' => [
            'certificate' => env('ZATCA_CERTIFICATE'),
            'certificate_path' => env('ZATCA_CERTIFICATE_PATH'),
            'private_key' => env('ZATCA_PRIVATE_KEY'),
            'private_key_path' => env('ZATCA_PRIVATE_KEY_PATH'),
            'secret' => env('ZATCA_SECRET'),
        ],
        'api' => [
            'base_url' => env('ZATCA_API_BASE_URL'),
            'compliance_url' => env('ZATCA_API_COMPLIANCE_URL'),
            'clearance_url' => env('ZATCA_API_CLEARANCE_URL'),
            'reporting_url' => env('ZATCA_API_REPORTING_URL'),
            'compliance_csid_url' => env('ZATCA_API_COMPLIANCE_CSID_URL'),
            'production_csid_url' => env('ZATCA_API_PRODUCTION_CSID_URL'),
            'compliance_checks_url' => env('ZATCA_API_COMPLIANCE_CHECKS_URL'),
            'client_id' => env('ZATCA_API_CLIENT_ID'),
            'client_secret' => env('ZATCA_API_CLIENT_SECRET'),
            'binary_security_token' => env('ZATCA_BINARY_SECURITY_TOKEN'),
            'secret' => env('ZATCA_API_SECRET'),
            'accept_version' => env('ZATCA_API_ACCEPT_VERSION', 'V2'),
            'accept_language' => env('ZATCA_API_ACCEPT_LANGUAGE', env('ZATCA_LANGUAGE', 'en')),
            'clearance_status' => [
                'reporting' => env('ZATCA_REPORTING_CLEARANCE_STATUS', '0'),
                'clearance' => env('ZATCA_CLEARANCE_STATUS', '1'),
            ],
            'timeout' => (int) env('ZATCA_API_TIMEOUT', 30),
        ],
        'features' => [
            'phase1' => (bool) env('ZATCA_PHASE1_ENABLED', true),
            'phase2' => (bool) env('ZATCA_PHASE2_ENABLED', true),
            'multi_tenant' => (bool) env('ZATCA_MULTI_TENANT', false),
        ],
        'meta' => [
            'csr_defaults' => array_filter([
                'common_name' => env('ZATCA_CSR_COMMON_NAME'),
                'serial_number_prefix' => env('ZATCA_CSR_SERIAL_PREFIX'),
                'organization_identifier' => env('ZATCA_CSR_ORGANIZATION_IDENTIFIER'),
                'organization_name' => env('ZATCA_CSR_ORGANIZATION_NAME'),
                'organization_unit_name' => env('ZATCA_CSR_ORGANIZATION_UNIT_NAME'),
                'country_name' => env('ZATCA_CSR_COUNTRY_NAME'),
                'invoice_type' => env('ZATCA_CSR_INVOICE_TYPE'),
                'location_address' => env('ZATCA_CSR_LOCATION_ADDRESS'),
                'industry_business_category' => env('ZATCA_CSR_INDUSTRY_BUSINESS_CATEGORY'),
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution
    |--------------------------------------------------------------------------
    |
    | Bind your own resolver and repository in the host application when you
    | need custom tenant discovery or configuration loading.
    |
    */
    'tenant' => [
        'resolver' => null,
        'repository' => DatabaseTenantConfigRepository::class,
        'credential_store' => null,
        'require_explicit_tenant' => (bool) env('ZATCA_REQUIRE_EXPLICIT_TENANT', false),
        'auth' => [
            'guard' => env('ZATCA_TENANT_AUTH_GUARD'),
            'guests_are_admin' => (bool) env('ZATCA_TENANT_GUESTS_ARE_ADMIN', true),
            'user_key_candidates' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZATCA_TENANT_USER_KEY_CANDIDATES', 'zatca_tenant_key,tenant_key,tenant_id'))))),
            'user_method_candidates' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZATCA_TENANT_USER_METHOD_CANDIDATES', 'zatcaTenantKey,tenantKey,tenantId'))))),
            'admin_ability' => env('ZATCA_TENANT_ADMIN_ABILITY'),
            'admin_property_candidates' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZATCA_TENANT_ADMIN_PROPERTY_CANDIDATES', 'is_super_admin,is_admin'))))),
            'admin_method_candidates' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZATCA_TENANT_ADMIN_METHOD_CANDIDATES', 'isSuperAdmin,isAdmin'))))),
        ],
        'request' => [
            'header' => env('ZATCA_TENANT_HEADER', 'X-Tenant-Key'),
            'query_parameter' => env('ZATCA_TENANT_QUERY_PARAMETER', 'tenant'),
            'route_parameter' => env('ZATCA_TENANT_ROUTE_PARAMETER', 'tenant'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Static Tenant Definitions
    |--------------------------------------------------------------------------
    |
    | This array can be used by smaller applications that do not need a custom
    | tenant repository yet. Larger SaaS apps should replace this with a custom
    | repository implementation.
    |
    */
    'tenants' => [
        // 'tenant-1' => [
        //     'seller_name' => 'Demo Seller',
        //     'seller_vat_number' => '300000000000003',
        // ],
    ],

    'cache' => [
        'enabled' => (bool) env('ZATCA_CACHE_ENABLED', false),
        'store' => env('ZATCA_CACHE_STORE'),
        'ttl' => (int) env('ZATCA_CACHE_TTL', 300),
    ],

    'phase2' => [
        'signer' => env('ZATCA_PHASE2_SIGNER', 'php'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SDK / Validation Resources
    |--------------------------------------------------------------------------
    |
    | These paths let a host app point the package at the official SDK assets
    | during development and sandbox certification. They are optional because
    | production Laravel apps should not be forced to ship the .NET SDK.
    |
    */
    'sdk' => [
        'path' => env('ZATCA_SDK_PATH', realpath(__DIR__ . '/../.net sdk/zatca-einvoicing-sdk-DotNet-238-R3.4.8') ?: null),
        'cli_path' => env('ZATCA_SDK_CLI_PATH'),
        'resources' => [
            'pih_path' => env('ZATCA_PIH_PATH'),
            'invoice_xsd_path' => env('ZATCA_INVOICE_XSD_PATH'),
            'zatca_schematron_path' => env('ZATCA_ZATCA_SCHEMATRON_PATH'),
            'en16931_schematron_path' => env('ZATCA_EN16931_SCHEMATRON_PATH'),
            'standard_sample_path' => env('ZATCA_STANDARD_SAMPLE_PATH'),
            'simplified_sample_path' => env('ZATCA_SIMPLIFIED_SAMPLE_PATH'),
        ],
    ],

    'logging' => [
        'enabled' => (bool) env('ZATCA_LOGGING_ENABLED', true),
        'channel' => env('ZATCA_LOG_CHANNEL'),
    ],

    'commands' => [
        'enabled' => (bool) env('ZATCA_COMMANDS_ENABLED', true),
    ],

    'onboarding' => [
        'simple_mode' => [
            'enabled' => (bool) env('ZATCA_ONBOARDING_SIMPLE_MODE', true),
            'tenant_key' => env('ZATCA_ONBOARDING_SIMPLE_TENANT_KEY', env('ZATCA_DEFAULT_TENANT_KEY', 'default')),
            'show_notification_hooks' => (bool) env('ZATCA_ONBOARDING_SIMPLE_SHOW_NOTIFICATION_HOOKS', false),
        ],
        'api' => [
            'enabled' => (bool) env('ZATCA_ONBOARDING_API_ENABLED', true),
            'prefix' => env('ZATCA_ONBOARDING_API_PREFIX', 'api/zatca/onboarding'),
            'require_auth' => (bool) env('ZATCA_ONBOARDING_API_REQUIRE_AUTH', false),
            'auth_middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZATCA_ONBOARDING_API_AUTH_MIDDLEWARE', 'auth'))))),
            'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZATCA_ONBOARDING_API_MIDDLEWARE', 'api'))))),
        ],
        'dashboard' => [
            'enabled' => (bool) env('ZATCA_ONBOARDING_DASHBOARD_ENABLED', true),
            'prefix' => env('ZATCA_ONBOARDING_DASHBOARD_PREFIX', 'zatca/onboarding/dashboard'),
            'require_auth' => (bool) env('ZATCA_ONBOARDING_DASHBOARD_REQUIRE_AUTH', false),
            'auth_middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZATCA_ONBOARDING_DASHBOARD_AUTH_MIDDLEWARE', 'auth'))))),
            'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZATCA_ONBOARDING_DASHBOARD_MIDDLEWARE', 'web'))))),
            'show_tenant_switcher_for_tenant_users' => (bool) env('ZATCA_ONBOARDING_DASHBOARD_SHOW_TENANT_SWITCHER_FOR_TENANT_USERS', false),
        ],
    ],

    'health' => [
        'certificate_expiry_warning_days' => (int) env('ZATCA_CERTIFICATE_EXPIRY_WARNING_DAYS', 30),
        'monitor' => [
            'enabled' => (bool) env('ZATCA_HEALTH_MONITOR_ENABLED', false),
            'cron' => env('ZATCA_HEALTH_MONITOR_CRON', '0 * * * *'),
            'minimum_severity' => env('ZATCA_HEALTH_MONITOR_MINIMUM_SEVERITY', 'warning'),
            'fail_on' => env('ZATCA_HEALTH_MONITOR_FAIL_ON', 'error'),
            'show_issues' => (bool) env('ZATCA_HEALTH_MONITOR_SHOW_ISSUES', false),
        ],
    ],
];
