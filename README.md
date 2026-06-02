# maaz/laravel-zatca

`maaz/laravel-zatca` is a Laravel package for integrating ZATCA Phase 1 and Phase 2 in a developer-friendly, tenant-aware way.

The package is designed for:

- Laravel applications that need ZATCA QR generation
- teams building invoice generation and submission workflows
- SaaS products that need per-tenant ZATCA credentials and configuration

## Features

- Fluent invoice builder
- ZATCA Phase 1 QR generation using TLV + Base64
- UBL 2.1 invoice XML generation
- XML signing with ECDSA private key
- SDK-backed CSR generation for sandbox onboarding
- Clearance and reporting submission flow
- Tenant-aware configuration
- Encrypted-at-rest tenant credential storage for private keys and CSID secrets
- English and Arabic localization
- Configurable debug logging
- PHPUnit test coverage for core package flows

## Installation

Install the package with Composer:

```bash
composer require maaz/laravel-zatca
```

Publish configuration and language files:

```bash
php artisan zatca:install
```

## Requirements

- PHP 8.1+
- Laravel 10, 11, 12, or 13
- `ext-dom`
- `ext-json`
- `ext-libxml`
- `ext-openssl`

If you want to use the official .NET SDK helper features bundled by this package, also install:

- .NET 8 Runtime or SDK

## Configuration

After publishing, the main configuration file will be available at:

```php
config/zatca.php
```

### Basic Single-Tenant Setup

Example `.env` values:

```env
ZATCA_ENVIRONMENT=sandbox
ZATCA_SELLER_NAME="Maaz Store"
ZATCA_SELLER_VAT_NUMBER=300000000000003

ZATCA_CERTIFICATE="-----BEGIN CERTIFICATE-----..."
ZATCA_CERTIFICATE_PATH=storage/app/zatca/certificate.pem
ZATCA_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----..."
ZATCA_PRIVATE_KEY_PATH=storage/app/zatca/private-key.pem
ZATCA_SECRET=your-private-key-passphrase

# Recommended for strict sandbox validation and SDK-backed signing / CSR generation
ZATCA_PHASE2_SIGNER=sdk
ZATCA_SDK_PATH="F:/path/to/zatca-einvoicing-sdk"

ZATCA_API_BASE_URL=https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal
ZATCA_API_ACCEPT_VERSION=V2
ZATCA_API_ACCEPT_LANGUAGE=en
ZATCA_REPORTING_CLEARANCE_STATUS=0
ZATCA_CLEARANCE_STATUS=1

# Use the Production CSID response values for real reporting/clearance calls.
ZATCA_BINARY_SECURITY_TOKEN=your-production-binary-security-token
ZATCA_API_SECRET=your-production-secret

ZATCA_DEBUG=true
ZATCA_LOGGING_ENABLED=true
```

### Important Config Areas

`default_tenant`
- default seller and tenant values for single-tenant apps

`tenant`
- resolver and repository configuration for multi-tenant systems

`tenants`
- optional static tenant array for smaller apps

`debug`
- enables debug-level package logging

`logging`
- controls package logging and log channel

### Sandbox API Defaults

The ZATCA developer portal sandbox currently expects these request headers:

- `Accept-Version: V2`
- `accept-language: en` or `ar`
- `Clearance-Status: 0` for reporting simplified invoices
- `Clearance-Status: 1` for clearance standard invoices

These are configured through `ZATCA_API_ACCEPT_VERSION`, `ZATCA_API_ACCEPT_LANGUAGE`, `ZATCA_REPORTING_CLEARANCE_STATUS`, and `ZATCA_CLEARANCE_STATUS`.

### Supported Credential Inputs

The package can load credentials from config using:

- `certificates.certificate`
- `certificates.private_key`
- `certificates.secret`

You can also extend this with your own repository and credential loading strategy.

## Quick Start

### Build an Invoice

```php
use Maaz\LaravelZatca\Facades\Zatca;

$invoice = Zatca::invoice()
    ->invoiceNumber('INV-1001')
    ->seller([
        'name' => 'Maaz Store',
        'vat_number' => '300000000000003',
        'street' => 'King Road',
        'city' => 'Riyadh',
        'postal_code' => '12345',
    ])
    ->buyer([
        'name' => 'Customer One',
        'vat_number' => '300000000000004',
        'street' => 'Buyer Street',
        'city' => 'Jeddah',
        'postal_code' => '54321',
    ])
    ->addItem([
        'name' => 'Product A',
        'quantity' => 2,
        'unit_price' => 100,
        'tax_percent' => 15,
    ])
    ->addItem([
        'name' => 'Product B',
        'quantity' => 1,
        'unit_price' => 50,
        'tax_percent' => 15,
    ])
    ->generate();
```

The returned object is an `InvoiceData` DTO.

## Usage Examples

### Validate an Invoice

```php
$result = Zatca::validate($invoice);
```

### Generate Phase 1 QR Code

```php
$qr = Zatca::generateQr($invoice);
```

Or directly from the builder:

```php
$qr = Zatca::invoice()
    ->seller([
        'name' => 'Maaz Store',
        'vat_number' => '300000000000003',
    ])
    ->addItem([
        'name' => 'Product A',
        'quantity' => 1,
        'unit_price' => 100,
        'tax_percent' => 15,
    ])
    ->generateQr();
```

### Generate UBL XML

```php
$xml = Zatca::generateXml($invoice);
```

### Sign XML

```php
$signedXml = Zatca::sign($invoice);
```

### Prepare Final Phase 2 XML Without Submission

Use `prepare()` when you want the exact QR-injected XML and API payload before calling the sandbox:

```php
$prepared = Zatca::prepare($invoice);

$prepared->xml;          // Raw UBL XML
$prepared->signedXml;    // Signed XML before QR injection
$prepared->finalXml;     // Final signed XML with embedded QR
$prepared->qrCode;       // Phase 2 QR payload
$prepared->invoiceHash;  // Invoice hash used in QR and API payload
$prepared->apiPayload(); // Ready-to-submit API body
```

Or directly from the builder:

```php
$prepared = Zatca::invoice()
    ->invoiceNumber('INV-3001')
    ->seller([
        'name' => 'Maaz Store',
        'vat_number' => '300000000000003',
    ])
    ->addItem([
        'name' => 'Retail Sale',
        'quantity' => 1,
        'unit_price' => 150,
        'tax_percent' => 15,
    ])
    ->prepare();
```

### Generate Sandbox Onboarding CSR

Use the official SDK-backed command to generate the CSR and private key needed for Compliance CSID onboarding:

```bash
php artisan zatca:csr-generate ^
  --common-name="TST-886431145-399999999900003" ^
   --serial-number="1-TST/2-TST/3-ed22f1d8-e6a2-1118-9b58-d9a8f11e445f" ^
  --location-address="RRRD2929" ^
  --industry-business-category="Supply activities"
```

By default the command generates PEM files under `storage/app/private/zatca/onboarding`.

Useful options:

- `--config=path/to/csr.properties` to reuse an existing SDK CSR properties file
- `--save-config=path/to/csr.properties` to save the generated properties file
- `--raw` to keep the SDK raw output format instead of PEM
- `--sim` or `--nonprod` to target simulation or non-production CSR generation modes
- `--show-csr` to print the normalized base64 CSR value for the Compliance CSID API

The command uses tenant defaults for:

- `organization_identifier` -> seller VAT number
- `organization_unit_name` -> branch name
- `organization_name` -> seller name

### Request Compliance CSID

After generating the CSR, request the sandbox Compliance CSID:

```bash
php artisan zatca:compliance-csid 123345 \
  --csr-file="storage/app/private/zatca/onboarding/generated-csr-20260419_072046-b8aadba5.csr" \
  --save="storage/app/private/zatca/onboarding/compliance-csid.json"
```

The command accepts either:

- `--csr-file` with a PEM or base64 CSR file
- `--csr` with the base64 CSR directly

On success it prints:

- `requestID`
- `dispositionMessage`
- `binarySecurityToken`
- `secret`

Save those values because the next onboarding step, `Production CSID`, needs them.

### Request Production CSID

Once Compliance CSID is issued, request the sandbox Production CSID:

```bash
php artisan zatca:production-csid \
  --compliance-response="storage/app/private/zatca/onboarding/compliance-csid.json" \
  --save="storage/app/private/zatca/onboarding/production-csid.json"
```

You can also pass values directly:

```bash
php artisan zatca:production-csid \
  --request-id="1234567890123" \
  --binary-security-token="..." \
  --secret="..."
```

Use the Production CSID `binarySecurityToken` and `secret` for Reporting/Clearance API credentials.

### Run Compliance Invoice Check

Before reporting or clearance, submit a signed sample invoice to the Compliance Invoice API using the Compliance CSID response and the private key generated with the CSR:

```bash
php artisan zatca:compliance-check-sample \
  --compliance-response="storage/app/private/zatca/onboarding/compliance-csid.json" \
  --private-key="storage/app/private/zatca/onboarding/generated-private-key.pem" \
  --seller-name="BI Technology Company" \
  --seller-vat="313138851500003" \
  --seller-crn="7050816433" \
  --street="Saidya" \
  --building-number="7036" \
  --additional-number="7036" \
  --district="AL Duraihemiyah" \
  --city="Riyadh" \
  --postal-code="12796" \
  --save="storage/app/private/zatca/onboarding/compliance-check.json" \
  --save-xml="storage/app/private/zatca/onboarding/compliance-check.xml"
```

This command temporarily uses the Compliance CSID certificate/secret for signing and authentication, then submits the generated sample invoice to `/compliance/invoices`.

### Submit Sample Reporting or Clearance Invoice

After Production CSID is issued, use it with the generated private key to test the real submission endpoints:

```bash
php artisan zatca:submit-sample \
  --mode=reporting \
  --production-response="storage/app/private/zatca/onboarding/production-csid.json" \
  --private-key="storage/app/private/zatca/onboarding/generated-private-key.pem" \
  --seller-name="BI Technology Company" \
  --seller-vat="313138851500003" \
  --seller-crn="7050816433" \
  --street="Saidya" \
  --building-number="7036" \
  --additional-number="7036" \
  --district="AL Duraihemiyah" \
  --city="Riyadh" \
  --postal-code="12796" \
  --save="storage/app/private/zatca/onboarding/reporting-submit.json" \
  --save-xml="storage/app/private/zatca/onboarding/reporting-submit.xml"
```

For a standard invoice clearance call, switch the mode:

```bash
php artisan zatca:submit-sample \
  --mode=clearance \
  --production-response="storage/app/private/zatca/onboarding/production-csid.json" \
  --private-key="storage/app/private/zatca/onboarding/generated-private-key.pem"
```

The command temporarily uses the Production CSID token and secret for authentication, signs the sample invoice with the same private key used during onboarding, and submits the final QR-injected XML to the requested API.

### Submit Invoice

```php
$result = Zatca::submit($invoice, 'clearance');
```

Or with the builder:

```php
$result = Zatca::invoice()
    ->seller([
        'name' => 'Maaz Store',
        'vat_number' => '300000000000003',
    ])
    ->addItem([
        'name' => 'Product A',
        'quantity' => 1,
        'unit_price' => 100,
        'tax_percent' => 15,
    ])
    ->submit();
```

## Submission Result

`submit()` returns a `SubmissionResult` DTO.

It contains:

- `invoice`
- `mode`
- `xml`
- `signedXml`
- `qrCode`
- `invoiceHash`
- `apiResponse`
- `tenantConfig`

Example:

```php
$result = Zatca::submit($invoice, 'clearance');

if ($result->accepted()) {
    $data = $result->toArray();
}
```

## Multi-Tenant Usage

The package is tenant-aware by design.

You can use it in:

- single-tenant Laravel applications
- custom SaaS applications
- apps using custom tenant resolution

### Explicit Tenant Usage

```php
$result = Zatca::forTenant($tenant)
    ->invoice()
    ->seller([
        'name' => 'Tenant Seller',
        'vat_number' => '300000000000003',
    ])
    ->addItem([
        'name' => 'Subscription Plan',
        'quantity' => 1,
        'unit_price' => 200,
        'tax_percent' => 15,
    ])
    ->submit('clearance');
```

`forTenant($tenant)` accepts flexible inputs such as:

- string tenant id
- integer tenant id
- array tenant payload
- object/model with `id` or `getKey()`
- `TenantContext`

### Static Tenant Config Example

You can define tenants directly in `config/zatca.php`:

```php
'tenants' => [
    'tenant-1' => [
        'seller_name' => 'Tenant One Store',
        'seller_vat_number' => '300000000000003',
        'environment' => 'sandbox',
        'certificates' => [
            'certificate' => env('TENANT_1_ZATCA_CERTIFICATE'),
            'private_key' => env('TENANT_1_ZATCA_PRIVATE_KEY'),
            'secret' => env('TENANT_1_ZATCA_SECRET'),
        ],
        'api' => [
            'client_id' => env('TENANT_1_ZATCA_CLIENT_ID'),
            'client_secret' => env('TENANT_1_ZATCA_CLIENT_SECRET'),
        ],
    ],
],
```

### Custom Tenant Resolution

For larger SaaS systems, bind your own implementations for:

- `TenantResolver`
- `TenantConfigRepository`

This lets you resolve tenant-specific:

- seller VAT number
- environment
- API credentials
- certificate/private key

### SaaS Tenant Schema

For a real SaaS setup, the package now includes publishable migrations for three aligned tables:

- `zatca_tenants`
- `zatca_tenant_credentials`
- `zatca_tenant_invoice_states`

Publish and run them with:

```bash
php artisan vendor:publish --tag=zatca-migrations
php artisan migrate
```

Recommended purpose of each table:

- `zatca_tenants`
  - tenant identity
  - legal/company profile
  - bilingual names (`*_ar`)
  - VAT / CRN
  - Saudi national address
  - default locale and environment
  - onboarding status

- `zatca_tenant_credentials`
  - sandbox / production onboarding state
  - CSR data
  - private key
  - compliance token + secret
  - production token + secret
  - validation timestamps

Sensitive credential fields in `zatca_tenant_credentials` are encrypted at rest through Laravel's encrypter, so database-backed SaaS tenants do not store private keys or CSID secrets as plain text. Keep your Laravel `APP_KEY` stable and protected because it is required to decrypt those values later.

### Rotating Encrypted Tenant Credentials After an APP_KEY Change

If you need to rotate the Laravel `APP_KEY`, re-encrypt stored tenant credentials with the new key after the application is updated.

Use:

```bash
php artisan zatca:rotate-credentials \
  --from="base64:OLD_APP_KEY_VALUE"
```

Useful options:

- `--from` accepts one or more previous APP_KEY values
- `--to` lets you target a specific new APP_KEY instead of the current app key
- `--tenant` limits the rotation to one tenant id or key
- `--dry-run` verifies that credentials can be decrypted and re-encrypted without writing changes

The command also checks Laravel `app.previous_keys` automatically, so you can stage a rotation by configuring previous keys first and then running the command.

- `zatca_tenant_invoice_states`
  - per-environment invoice counter (`ICV`)
  - previous invoice hash (`PIH`)
  - last submitted invoice references

This structure keeps tenant profile, secrets, and invoice-chain state separate, which is important for SaaS isolation and auditing.

### Database Repository and Request Resolver

The package now also includes optional SaaS helpers:

- `Maaz\LaravelZatca\Tenancy\Repositories\DatabaseTenantConfigRepository`
- `Maaz\LaravelZatca\Tenancy\Resolvers\RequestTenantResolver`

Use them in `config/zatca.php`:

```php
'tenant' => [
    'resolver' => \Maaz\LaravelZatca\Tenancy\Resolvers\RequestTenantResolver::class,
    'repository' => \Maaz\LaravelZatca\Tenancy\Repositories\DatabaseTenantConfigRepository::class,
],
```

`RequestTenantResolver` can resolve the active tenant from:

- request header: `X-Tenant-Key`
- query string: `tenant`
- route parameter: `tenant`

These names are configurable through:

- `ZATCA_TENANT_HEADER`
- `ZATCA_TENANT_QUERY_PARAMETER`
- `ZATCA_TENANT_ROUTE_PARAMETER`

The database repository maps the active tenant into a runtime `TenantConfig`, including:

- seller identity
- locale (`en` / `ar`)
- branch name
- active certificate/token
- API secret
- next `ICV`
- current `PIH`

This gives a host SaaS app a clean starting point for bilingual tenant onboarding and per-tenant invoice submission flows.

### Tenant Onboarding API Flow

The package now includes a package API for tenant onboarding that a SaaS frontend can consume in either English or Arabic.

Default base prefix:

```text
/api/zatca/onboarding
```

Default middleware:

```text
api
```

Both are configurable through:

- `ZATCA_ONBOARDING_API_ENABLED`
- `ZATCA_ONBOARDING_API_PREFIX`
- `ZATCA_ONBOARDING_API_MIDDLEWARE`

Available endpoints:

- `POST /tenants`
- `GET /tenants`
- `GET /tenants/{tenant}`
- `PUT|PATCH /tenants/{tenant}`
- `POST /tenants/{tenant}/csr`
- `POST /tenants/{tenant}/compliance-csid`
- `POST /tenants/{tenant}/production-csid`
- `GET /tenants/{tenant}/invoices`
- `POST /tenants/{tenant}/invoices`
- `GET /tenants/{tenant}/invoices/{invoice}`
- `GET /tenants/{tenant}/invoices/{invoice}/xml`
- `GET /tenants/{tenant}/invoices/{invoice}/signed-xml`
- `GET /tenants/{tenant}/invoices/{invoice}/api-response`

The tenant response payload includes:

- English and Arabic company names
- seller and branch names
- VAT / CRN
- Saudi address
- onboarding status
- bilingual onboarding status labels
- credential availability per environment
- invoice state including next `ICV` and current `PIH`

This lets a host app build a bilingual onboarding UI without duplicating tenant/ZATCA state logic in the frontend.

### Built-in Onboarding Dashboard UI

The package now also includes a bilingual onboarding dashboard view for host Laravel apps.

Default route:

```text
/zatca/onboarding/dashboard
```

Tenant-specific route:

```text
/zatca/onboarding/dashboard/{tenant}
```

Configuration:

```env
ZATCA_ONBOARDING_DASHBOARD_ENABLED=true
ZATCA_ONBOARDING_DASHBOARD_PREFIX="zatca/onboarding/dashboard"
ZATCA_ONBOARDING_DASHBOARD_MIDDLEWARE="web"
```

The dashboard:

- lists all tenants
- supports English and Arabic through `?lang=en` and `?lang=ar`
- shows onboarding progress and live credential health
- allows profile editing
- allows CSR generation
- allows Compliance CSID issuance
- allows Production CSID issuance
- includes a tenant invoice submission panel
- shows recent reporting and clearance history per tenant
- opens an invoice detail drawer with saved XML, signed XML, and raw API response
- exposes direct download actions for XML artifacts and API payloads
- includes tenant search plus invoice search/filter controls
- supports invoice date-range filtering and server-backed pagination for longer histories
- surfaces tenant health issues and failed invoice submissions in an alerts panel
- lets operators manage webhook notification hooks for health alerts and failed submissions
- renders API responses in-place for operational debugging

It is implemented as a package Blade view with plain JavaScript, so a host app can use it without setting up a separate frontend build step.

Submitted tenant invoices are stored in:

- `zatca_tenant_invoices`

That table keeps a lightweight operational history of:

- invoice number and UUID
- environment and mode
- reporting / clearance status
- invoice hash and QR payload
- seller / buyer / items snapshot
- signed XML and API response payload

Each credential entry in the onboarding payload now also includes a live `health` block with:

- overall status: `healthy`, `warning`, or `error`
- bilingual status labels
- certificate source and expiry details
- detected issues such as:
  - missing private key
  - missing CSID token or secret
  - invalid authentication certificate
  - certificate VAT mismatch
  - expired or soon-to-expire certificates

You can also run an operational check from the console:

```bash
php artisan zatca:tenant-health --show-issues
```

Useful options:

- `--tenant=bi-tech` to scope the check to one tenant
- `--show-issues` to print each issue in detail

The warning threshold for certificate expiry is configurable through:

```env
ZATCA_CERTIFICATE_EXPIRY_WARNING_DAYS=30
```

Whenever a health check runs, the package now updates `last_validated_at` on the matching tenant credential row automatically.

### Scheduled Tenant Health Monitoring

For recurring monitoring and alert fan-out, use:

```bash
php artisan zatca:tenant-health-monitor --show-issues
```

The monitor command:

- reuses the same health analysis logic
- updates `last_validated_at`
- dispatches a `TenantCredentialHealthAlertDetected` event for warning or error items
- supports exit-code gating for scheduler integrations

Useful options:

- `--minimum-severity=warning` to alert on warnings and errors
- `--minimum-severity=error` to alert only on errors
- `--fail-on=warning`
- `--fail-on=error`
- `--fail-on=never`
- `--tenant=bi-tech`

You can enable automatic scheduler registration through config:

```env
ZATCA_HEALTH_MONITOR_ENABLED=true
ZATCA_HEALTH_MONITOR_CRON="0 * * * *"
ZATCA_HEALTH_MONITOR_MINIMUM_SEVERITY=warning
ZATCA_HEALTH_MONITOR_FAIL_ON=error
ZATCA_HEALTH_MONITOR_SHOW_ISSUES=false
```

When enabled, the package registers the monitor command with Laravel's scheduler using the configured cron expression.

To send real alerts, let your app listen for:

```php
Maaz\LaravelZatca\Events\TenantCredentialHealthAlertDetected
```

That event contains the tenant, the credential record, and the computed health payload so you can wire notifications, logging, or incident workflows in the host app.

## API Submission Example

### Clearance

```php
$result = Zatca::invoice()
    ->invoiceNumber('INV-2001')
    ->seller([
        'name' => 'Maaz Store',
        'vat_number' => '300000000000003',
    ])
    ->buyer([
        'name' => 'Corporate Buyer',
        'vat_number' => '300000000000004',
    ])
    ->addItem([
        'name' => 'Consulting Service',
        'quantity' => 1,
        'unit_price' => 1000,
        'tax_percent' => 15,
    ])
    ->submit('clearance');
```

### Reporting

```php
$result = Zatca::invoice()
    ->invoiceNumber('INV-2002')
    ->seller([
        'name' => 'Maaz Store',
        'vat_number' => '300000000000003',
    ])
    ->addItem([
        'name' => 'Retail Sale',
        'quantity' => 1,
        'unit_price' => 150,
        'tax_percent' => 15,
    ])
    ->report();
```

## Validation

The package validates:

- required seller fields
- seller VAT format
- invoice items
- totals consistency

Validation failures throw `ValidationException`.

Example:

```php
use Maaz\LaravelZatca\Exceptions\ValidationException;

try {
    $invoice = Zatca::invoice()->generate();
} catch (ValidationException $exception) {
    $errors = $exception->errors;
}
```

## Localization

The package supports:

- English
- Arabic

It uses Laravel language files and follows the app locale.

Example:

```php
app()->setLocale('ar');
```

Then package validation and exception messages will be returned in Arabic where translations are available.

## Host App Authentication

For a real SaaS setup, the package expects your host application to own:

- user registration
- login
- roles / permissions
- tenant membership

The package can now plug into that flow in two ways:

1. resolve the active tenant from the authenticated user
2. require authentication for the onboarding dashboard and API routes

### Recommended user model setup

Your host app user model can implement [`Maaz\LaravelZatca\Contracts\TenantAwareUser`](F:\Laravel-package\src\Contracts\TenantAwareUser.php) directly, or use the included trait [`Maaz\LaravelZatca\Concerns\InteractsWithZatcaTenantWorkspace`](F:\Laravel-package\src\Concerns\InteractsWithZatcaTenantWorkspace.php).

### Recommended user table columns

Your host app `users` table should include at least:

- `tenant_key`
- `is_super_admin`

Example migration in the host app:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('tenant_key')->nullable()->index();
            $table->boolean('is_super_admin')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['tenant_key', 'is_super_admin']);
        });
    }
};
```

Example:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Maaz\LaravelZatca\Concerns\InteractsWithZatcaTenantWorkspace;
use Maaz\LaravelZatca\Contracts\TenantAwareUser;

class User extends Authenticatable implements TenantAwareUser
{
    use InteractsWithZatcaTenantWorkspace;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_key',
        'is_super_admin',
    ];
}
```

With that in place:

- tenant users are resolved automatically from `tenant_key`
- super admins can be identified from `is_super_admin`

If your app uses different field names or methods, the package also supports configuration-based fallbacks in `config/zatca.php`.

### Require authentication for package routes

In your host app `.env`:

```env
ZATCA_ONBOARDING_DASHBOARD_REQUIRE_AUTH=true
ZATCA_ONBOARDING_API_REQUIRE_AUTH=true
```

If you use a custom auth guard or middleware stack:

```env
ZATCA_TENANT_AUTH_GUARD=web
ZATCA_ONBOARDING_DASHBOARD_AUTH_MIDDLEWARE=auth:web
ZATCA_ONBOARDING_API_AUTH_MIDDLEWARE=auth:web
```

This gives you the typical SaaS behavior:

- super admin can see all tenants and create tenants
- tenant users only see their own tenant workspace
- tenant users cannot create tenants or access other tenants

### Minimum host app checklist

Before testing in the host app:

1. add `tenant_key` and `is_super_admin` to your users table
2. update the host app `User` model with the package trait or contract
3. set:
   - `ZATCA_ONBOARDING_DASHBOARD_REQUIRE_AUTH=true`
   - `ZATCA_ONBOARDING_API_REQUIRE_AUTH=true`
4. if needed, set:
   - `ZATCA_TENANT_AUTH_GUARD`
   - `ZATCA_ONBOARDING_DASHBOARD_AUTH_MIDDLEWARE`
   - `ZATCA_ONBOARDING_API_AUTH_MIDDLEWARE`
5. log in as:
   - one super admin
   - one tenant-scoped user
6. verify that:
   - super admin sees all tenants and `New Tenant`
   - tenant user lands in only their tenant workspace
   - tenant user cannot open another tenant route manually

## Logging

The package includes configurable logging for:

- XML requests
- API responses
- package errors

Configuration:

```php
'debug' => true,

'logging' => [
    'enabled' => true,
    'channel' => env('ZATCA_LOG_CHANNEL'),
],
```

Debug XML and API payload logging should only be enabled in safe environments because invoice data may contain sensitive business information.

## Testing

The package includes PHPUnit tests for:

- invoice builder
- QR generation
- XML generation
- mocked API client flow

Run tests with:

```bash
composer test
```

## Current Notes

The package already includes the main building blocks for:

- invoice building
- QR generation
- XML generation
- signing
- API submission
- tenant-aware orchestration

### Sandbox Note

During sandbox testing, the Production CSID returned by ZATCA may decode to a generic or sample identity instead of the exact company identity used during Compliance CSID onboarding.

In practice, this means:

- Compliance CSID may correctly reflect the company VAT used in the CSR
- Production CSID may still resolve to a sample sandbox certificate identity
- Reporting and Clearance submissions will only succeed when the invoice seller VAT matches the VAT encoded in the authentication certificate

The package now performs a preflight VAT check against the authentication certificate before Reporting or Clearance submission so this mismatch is caught early with a clear error message.

For real production onboarding, always verify that:

- the invoice seller VAT matches the VAT encoded in the authentication certificate
- the CSR, Compliance CSID, and Production CSID all stay aligned to the same taxpayer identity
- sandbox behavior is not assumed to be identical to production identity issuance

For strict live production rollout, you should still verify:

- exact ZATCA Phase 2 compliance requirements
- real certificate handling
- environment-specific API credentials
- end-to-end integration with valid sandbox/production accounts

## Release Checklist

Before publishing the package publicly, verify the following:

- `composer test` passes
- `composer validate --strict --no-check-publish` passes
- Phase 1 QR generation works with a real sample invoice
- `prepare()` generates final signed XML with embedded Phase 2 QR
- `zatca:sdk-validate` passes against the final XML
- `zatca:csr-generate` works in a fresh Laravel app
- `zatca:compliance-csid` works with a valid sandbox OTP
- `zatca:production-csid` works using the saved Compliance CSID response
- `zatca:compliance-check-sample` returns `PASS`
- `zatca:submit-sample --mode=reporting` returns `REPORTED`
- `zatca:submit-sample --mode=clearance` returns `CLEARED`
- the package throws a clear error when the invoice VAT does not match the authentication certificate VAT
- README examples use real command syntax for PowerShell and Laravel users
- secrets, tokens, OTPs, and private keys are not committed to the repository
- the `.NET SDK` is documented as optional for local validation, not as a production dependency
- sandbox identity mismatch behavior is documented as a known limitation or investigation item

## License

MIT
