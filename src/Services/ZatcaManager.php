<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Services;

use Maaz\LaravelZatca\Contracts\CsrGenerator;
use Maaz\LaravelZatca\Contracts\HashGenerator;
use Maaz\LaravelZatca\Contracts\InvoiceNormalizer;
use Maaz\LaravelZatca\Contracts\InvoiceSigner;
use Maaz\LaravelZatca\Contracts\InvoiceValidator;
use Maaz\LaravelZatca\Contracts\OnboardingClient;
use Maaz\LaravelZatca\Contracts\Phase2QrCodeGenerator;
use Maaz\LaravelZatca\Contracts\QrCodeGenerator;
use Illuminate\Support\Str;
use Maaz\LaravelZatca\Contracts\SubmissionPipeline;
use Maaz\LaravelZatca\Contracts\TenantConfigRepository;
use Maaz\LaravelZatca\Contracts\TenantResolver;
use Maaz\LaravelZatca\Contracts\XmlGenerator;
use Maaz\LaravelZatca\DTOs\GeneratedCsrResult;
use Maaz\LaravelZatca\DTOs\InvoiceData;
use Maaz\LaravelZatca\DTOs\PreparedInvoiceResult;
use Maaz\LaravelZatca\DTOs\SubmissionResult;
use Maaz\LaravelZatca\DTOs\TenantConfig;
use Maaz\LaravelZatca\DTOs\TenantContext;
use Maaz\LaravelZatca\Exceptions\ZatcaException;
use Maaz\LaravelZatca\Support\ZatcaLogger;

class ZatcaManager
{
    protected ?TenantContext $tenantContext = null;

    public function __construct(
        protected TenantResolver $tenantResolver,
        protected TenantConfigRepository $tenantConfigRepository,
        protected QrCodeGenerator $qrCodeGenerator,
        protected XmlGenerator $xmlGenerator,
        protected InvoiceSigner $invoiceSigner,
        protected InvoiceValidator $invoiceValidator,
        protected ZatcaLogger $logger,
        protected InvoiceNormalizer $invoiceNormalizer,
        protected SubmissionPipeline $submissionPipeline,
        protected HashGenerator $hashGenerator,
        protected OnboardingClient $onboardingClient,
        protected Phase2QrCodeGenerator $phase2QrCodeGenerator,
        protected CsrGenerator $csrGenerator
    ) {
    }

    public function forTenant(mixed $tenant): self
    {
        $clone = clone $this;
        $clone->tenantContext = TenantContext::fromMixed($tenant);

        return $clone;
    }

    public function usingTenant(TenantContext $tenantContext): self
    {
        $clone = clone $this;
        $clone->tenantContext = $tenantContext;

        return $clone;
    }

    public function invoice(array|InvoiceData $invoice = []): InvoiceBuilder
    {
        return new InvoiceBuilder($this, $invoice);
    }

    public function validate(array|InvoiceData $invoice, ?string $phase = null): array
    {
        $result = $this->invoiceValidator->validate($this->invoiceNormalizer->normalize($invoice));
        $result['phase'] = $phase ?? 'auto';
        $result['tenant_id'] = $this->tenantConfig()->tenantId;

        return $result;
    }

    public function generateQr(array|InvoiceData $invoice): string
    {
        $normalizedInvoice = $this->invoiceNormalizer->normalize($invoice);
        $tenantConfig = $this->tenantConfig();

        $this->logger->debug((string) trans('zatca::messages.log_generating_qr'), [
            'tenant_id' => $tenantConfig->tenantId,
            'invoice_number' => $normalizedInvoice->invoiceNumber,
            'uuid' => $normalizedInvoice->uuid,
        ]);

        return $this->qrCodeGenerator->generate($normalizedInvoice, $tenantConfig);
    }

    public function generatePhase2Qr(array|InvoiceData $invoice, string $signedXml, ?string $invoiceHash = null): string
    {
        $normalizedInvoice = $this->invoiceNormalizer->normalize($invoice);
        $tenantConfig = $this->tenantConfig();

        $this->logger->debug((string) trans('zatca::messages.log_generating_qr'), [
            'tenant_id' => $tenantConfig->tenantId,
            'phase' => 'phase2',
            'invoice_number' => $normalizedInvoice->invoiceNumber,
            'uuid' => $normalizedInvoice->uuid,
        ]);

        return $this->phase2QrCodeGenerator->generate(
            $normalizedInvoice,
            $tenantConfig,
            $signedXml,
            $invoiceHash
        );
    }

    public function generateXml(array|InvoiceData $invoice): string
    {
        $normalizedInvoice = $this->invoiceNormalizer->normalize($invoice);
        $tenantConfig = $this->tenantConfig();

        $this->logger->debug((string) trans('zatca::messages.log_generating_xml'), [
            'tenant_id' => $tenantConfig->tenantId,
            'invoice_number' => $normalizedInvoice->invoiceNumber,
            'uuid' => $normalizedInvoice->uuid,
        ]);

        return $this->xmlGenerator->generate($normalizedInvoice, $tenantConfig);
    }

    public function sign(array|InvoiceData $invoice): string
    {
        $this->logger->debug((string) trans('zatca::messages.log_signing_xml'), [
            'tenant_id' => $this->tenantConfig()->tenantId,
        ]);

        return $this->invoiceSigner->sign(
            $this->generateXml($invoice),
            $this->tenantConfig()
        );
    }

    public function prepare(array|InvoiceData $invoice): PreparedInvoiceResult
    {
        $normalizedInvoice = $this->invoiceNormalizer->normalize($invoice);
        $tenantConfig = $this->tenantConfig();

        $this->logger->debug((string) trans('zatca::messages.log_generating_xml'), [
            'tenant_id' => $tenantConfig->tenantId,
            'phase' => 'phase2',
            'invoice_number' => $normalizedInvoice->invoiceNumber,
            'uuid' => $normalizedInvoice->uuid,
        ]);

        return $this->submissionPipeline->prepare($normalizedInvoice, $tenantConfig);
    }

    public function submit(array|InvoiceData $invoice, string $mode = 'clearance'): SubmissionResult
    {
        return $this->submissionPipeline->submit(
            $this->invoiceNormalizer->normalize($invoice),
            $this->tenantConfig(),
            $mode
        );
    }

    public function clearance(array|InvoiceData $invoice): SubmissionResult
    {
        return $this->submit($invoice, 'clearance');
    }

    public function report(array|InvoiceData $invoice): SubmissionResult
    {
        return $this->submit($invoice, 'reporting');
    }

    public function complianceCheck(array|InvoiceData $invoice): array
    {
        $normalizedInvoice = $this->invoiceNormalizer->normalize($invoice);
        $tenantConfig = $this->tenantConfig();
        $preparedInvoice = $this->submissionPipeline->prepare($normalizedInvoice, $tenantConfig);

        return $this->onboardingClient->complianceCheck($preparedInvoice->apiPayload(), $tenantConfig);
    }

    public function onboardComplianceCsid(array $payload = []): array
    {
        return $this->onboardingClient->requestComplianceCsid($payload, $this->tenantConfig());
    }

    public function onboardProductionCsid(array $payload = []): array
    {
        return $this->onboardingClient->requestProductionCsid($payload, $this->tenantConfig());
    }

    public function generateCsr(array $payload = []): GeneratedCsrResult
    {
        return $this->csrGenerator->generate($payload, $this->tenantConfig());
    }

    public function uuid(): string
    {
        return (string) Str::uuid();
    }

    public function hash(string $xml): string
    {
        return $this->hashGenerator->generate($xml);
    }

    public function tenantConfig(): TenantConfig
    {
        return $this->tenantConfigRepository->forTenant($this->resolveTenantContext());
    }

    protected function resolveTenantContext(): ?TenantContext
    {
        return $this->tenantContext ?? $this->tenantResolver->resolve();
    }
}
