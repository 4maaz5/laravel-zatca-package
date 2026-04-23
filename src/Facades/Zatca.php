<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Facades;

use Illuminate\Support\Facades\Facade;
use Maaz\LaravelZatca\DTOs\BuyerData;
use Maaz\LaravelZatca\DTOs\GeneratedCsrResult;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\InvoiceItemData;
use Maaz\LaravelZatca\DTOs\PreparedInvoiceResult;
use Maaz\LaravelZatca\DTOs\SellerData;
use Maaz\LaravelZatca\DTOs\SubmissionResult;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\DTOs\TenantContext;
use Maaz\LaravelZatca\Services\InvoiceBuilder;
use Maaz\LaravelZatca\Services\ZatcaManager;

/**
 * @method static ZatcaManager forTenant(mixed $tenant)
 * @method static ZatcaManager usingTenant(TenantContext $tenantContext)
 * @method static InvoiceBuilder invoice(array|InvoiceData $invoice = [])
 * @method static array validate(array|InvoiceData $invoice, ?string $phase = null)
 * @method static string generateQr(array|InvoiceData $invoice)
 * @method static string generatePhase2Qr(array|InvoiceData $invoice, string $signedXml, ?string $invoiceHash = null)
 * @method static string generateXml(array|InvoiceData $invoice)
 * @method static string sign(array|InvoiceData $invoice)
 * @method static PreparedInvoiceResult prepare(array|InvoiceData $invoice)
 * @method static SubmissionResult submit(array|InvoiceData $invoice, string $mode = 'clearance')
 * @method static SubmissionResult clearance(array|InvoiceData $invoice)
 * @method static SubmissionResult report(array|InvoiceData $invoice)
 * @method static array complianceCheck(array|InvoiceData $invoice)
 * @method static array onboardComplianceCsid(array $payload = [])
 * @method static array onboardProductionCsid(array $payload = [])
 * @method static GeneratedCsrResult generateCsr(array $payload = [])
 * @method static string uuid()
 * @method static string hash(string $xml)
 * @method static TenantConfig tenantConfig()
 *
 * @see \Maaz\LaravelZatca\Services\ZatcaManager
 */
class Zatca extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'zatca';
    }
}
