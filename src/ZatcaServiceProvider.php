<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Maaz\LaravelZatca\Commands\ComplianceCheckSampleCommand;
use Maaz\LaravelZatca\Commands\CheckTenantHealthCommand;
use Maaz\LaravelZatca\Commands\GenerateCsrCommand;
use Maaz\LaravelZatca\Commands\InstallCommand;
use Maaz\LaravelZatca\Commands\MonitorTenantHealthCommand;
use Maaz\LaravelZatca\Commands\RequestComplianceCsidCommand;
use Maaz\LaravelZatca\Commands\RequestProductionCsidCommand;
use Maaz\LaravelZatca\Commands\RotateTenantCredentialsCommand;
use Maaz\LaravelZatca\Commands\SdkValidateCommand;
use Maaz\LaravelZatca\Commands\SubmitSampleInvoiceCommand;
use Maaz\LaravelZatca\Contracts\ApiClient;
use Maaz\LaravelZatca\Contracts\CsrGenerator;
use Maaz\LaravelZatca\Contracts\HashGenerator;
use Maaz\LaravelZatca\Phase2\Onboarding\PhpCsrGenerator;
use Maaz\LaravelZatca\Contracts\InvoiceNormalizer;
use Maaz\LaravelZatca\Contracts\OnboardingClient;
use Maaz\LaravelZatca\Contracts\Phase2QrCodeGenerator;
use Maaz\LaravelZatca\Contracts\QrCodeGenerator;
use Maaz\LaravelZatca\Contracts\InvoiceValidator;
use Maaz\LaravelZatca\Contracts\SubmissionPipeline;
use Maaz\LaravelZatca\Contracts\TenantConfigRepository;
use Maaz\LaravelZatca\Contracts\TenantInvoiceStateStore;
use Maaz\LaravelZatca\Contracts\TenantResolver;
use Maaz\LaravelZatca\Contracts\InvoiceSigner;
use Maaz\LaravelZatca\Contracts\XmlGenerator;
use Maaz\LaravelZatca\Events\TenantCredentialHealthAlertDetected;
use Maaz\LaravelZatca\Events\TenantInvoiceSubmissionAlertDetected;
use Maaz\LaravelZatca\Phase1\Encoders\TlvEncoder;
use Maaz\LaravelZatca\Phase1\Services\QrCodeService;
use Maaz\LaravelZatca\Phase2\Api\FatooraApiClient;
use Maaz\LaravelZatca\Phase2\Api\FatooraOnboardingClient;
use Maaz\LaravelZatca\Phase2\Builders\UblInvoiceBuilder;
use Maaz\LaravelZatca\Phase2\Hashing\ZatcaInvoiceHashGenerator;
use Maaz\LaravelZatca\Phase2\Onboarding\SdkCsrGenerator;
use Maaz\LaravelZatca\Phase2\Qr\Phase2QrCodeService;
use Maaz\LaravelZatca\Phase2\Signatures\SdkSignatureService;
use Maaz\LaravelZatca\Phase2\Signatures\SignatureService;
use Maaz\LaravelZatca\Listeners\DispatchTenantHealthAlertToHooks;
use Maaz\LaravelZatca\Listeners\DispatchTenantInvoiceAlertToHooks;
use Maaz\LaravelZatca\Services\InvoiceNormalizer as DefaultInvoiceNormalizer;
use Maaz\LaravelZatca\Services\SubmissionPipeline as DefaultSubmissionPipeline;
use Maaz\LaravelZatca\Services\ZatcaManager;
use Maaz\LaravelZatca\Support\CertificateLoader;
use Maaz\LaravelZatca\Support\ZatcaLogger;
use Maaz\LaravelZatca\Tenancy\Onboarding\TenantOnboardingFlow;
use Maaz\LaravelZatca\Tenancy\Health\TenantCredentialHealthChecker;
use Maaz\LaravelZatca\Tenancy\Access\TenantAccessManager;
use Maaz\LaravelZatca\Tenancy\Invoices\TenantInvoiceSubmissionFlow;
use Maaz\LaravelZatca\Tenancy\Notifications\TenantNotificationHookDispatcher;
use Maaz\LaravelZatca\Tenancy\Repositories\ConfigTenantConfigRepository;
use Maaz\LaravelZatca\Tenancy\Repositories\DatabaseTenantConfigRepository;
use Maaz\LaravelZatca\Tenancy\Stores\DatabaseTenantInvoiceStateStore;
use Maaz\LaravelZatca\Tenancy\Stores\NullTenantInvoiceStateStore;
use Maaz\LaravelZatca\Tenancy\SimpleWorkspaceManager;
use Maaz\LaravelZatca\Tenancy\Resolvers\AuthenticatedUserTenantResolver;
use Maaz\LaravelZatca\Tenancy\Resolvers\CompositeTenantResolver;
use Maaz\LaravelZatca\Tenancy\Resolvers\NullTenantResolver;
use Maaz\LaravelZatca\Tenancy\Resolvers\RequestTenantResolver;
use Maaz\LaravelZatca\Tenancy\Security\CredentialRotationService;
use Maaz\LaravelZatca\Validation\InvoiceValidator as DefaultInvoiceValidator;

class ZatcaServiceProvider extends ServiceProvider
{
    protected const CONFIG_TAG = 'zatca-config';

    protected const LANG_TAG = 'zatca-lang';

    protected const INSTALL_TAG = 'zatca-install';

    protected const MIGRATIONS_TAG = 'zatca-migrations';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/zatca.php', 'zatca');

        $this->app->singleton(TenantResolver::class, function ($app): TenantResolver {
            $resolver = $app['config']->get('zatca.tenant.resolver');

            if (is_string($resolver) && $resolver !== '') {
                return $app->make($resolver);
            }

            return new CompositeTenantResolver([
                $app->make(AuthenticatedUserTenantResolver::class),
                $app->make(RequestTenantResolver::class),
                new NullTenantResolver(),
            ]);
        });

        $this->app->singleton(TenantConfigRepository::class, function ($app): TenantConfigRepository {
            $repository = $app['config']->get('zatca.tenant.repository', ConfigTenantConfigRepository::class);

            if (
                $repository === ConfigTenantConfigRepository::class
                && Schema::hasTable('zatca_tenants')
                && Schema::hasTable('zatca_tenant_credentials')
                && Schema::hasTable('zatca_tenant_invoice_states')
            ) {
                $repository = DatabaseTenantConfigRepository::class;
            }

            return $app->make($repository);
        });

        $this->app->singleton(TenantAccessManager::class, function ($app): TenantAccessManager {
            return new TenantAccessManager(
                $app->make(TenantResolver::class),
                $app->make(\Illuminate\Contracts\Auth\Factory::class)
            );
        });

        $this->app->singleton(TlvEncoder::class, function (): TlvEncoder {
            return new TlvEncoder();
        });

        $this->app->singleton(QrCodeGenerator::class, function ($app): QrCodeGenerator {
            return new QrCodeService(
                $app->make(TlvEncoder::class)
            );
        });

        $this->app->singleton(Phase2QrCodeGenerator::class, function ($app): Phase2QrCodeGenerator {
            return new Phase2QrCodeService(
                $app->make(TlvEncoder::class),
                $app->make(CertificateLoader::class),
                $app->make(HashGenerator::class)
            );
        });

        $this->app->singleton(InvoiceValidator::class, function (): InvoiceValidator {
            return new DefaultInvoiceValidator();
        });

        $this->app->singleton(InvoiceNormalizer::class, function ($app): InvoiceNormalizer {
            return new DefaultInvoiceNormalizer(
                $app->make(InvoiceValidator::class)
            );
        });

        $this->app->singleton(XmlGenerator::class, function (): XmlGenerator {
            return new UblInvoiceBuilder();
        });

        $this->app->singleton(CertificateLoader::class, function (): CertificateLoader {
            return new CertificateLoader();
        });

        $this->app->singleton(ZatcaLogger::class, function ($app): ZatcaLogger {
            return new ZatcaLogger(
                $app->make(\Illuminate\Log\LogManager::class)
            );
        });

        $this->app->singleton(InvoiceSigner::class, function ($app): InvoiceSigner {
            if ((string) $app['config']->get('zatca.phase2.signer', 'php') === 'sdk') {
                return new SdkSignatureService(
                    (array) $app['config']->get('zatca.sdk', []),
                    $app->make(CertificateLoader::class)
                );
            }

            return new SignatureService(
                $app->make(CertificateLoader::class),
                $app->make(HashGenerator::class)
            );
        });

        $this->app->singleton(ApiClient::class, function ($app): ApiClient {
            return new FatooraApiClient(
                $app->make(\Illuminate\Http\Client\Factory::class),
                $app->make(ZatcaLogger::class)
            );
        });

        $this->app->singleton(OnboardingClient::class, function ($app): OnboardingClient {
            return new FatooraOnboardingClient(
                $app->make(\Illuminate\Http\Client\Factory::class),
                $app->make(ZatcaLogger::class)
            );
        });

        $this->app->singleton(HashGenerator::class, function (): HashGenerator {
            return new ZatcaInvoiceHashGenerator();
        });

        $this->app->singleton(CsrGenerator::class, function ($app): CsrGenerator {
            $generator = (string) $app['config']->get('zatca.phase2.csr_generator', 'sdk');

            if ($generator === 'php') {
                return new PhpCsrGenerator();
            }

            return new SdkCsrGenerator(
                (array) $app['config']->get('zatca.sdk', [])
            );
        });

        $this->app->singleton(TenantInvoiceStateStore::class, function ($app): TenantInvoiceStateStore {
            $repository = $app['config']->get('zatca.tenant.repository', ConfigTenantConfigRepository::class);

            if ($repository === \Maaz\LaravelZatca\Tenancy\Repositories\DatabaseTenantConfigRepository::class) {
                return new DatabaseTenantInvoiceStateStore();
            }

            return new NullTenantInvoiceStateStore();
        });

        $this->app->singleton(SubmissionPipeline::class, function ($app): SubmissionPipeline {
            return new DefaultSubmissionPipeline(
                $app->make(XmlGenerator::class),
                $app->make(InvoiceSigner::class),
                $app->make(Phase2QrCodeGenerator::class),
                $app->make(ApiClient::class),
                $app->make(HashGenerator::class),
                $app->make(ZatcaLogger::class),
                $app->make(CertificateLoader::class),
                $app->make(TenantInvoiceStateStore::class)
            );
        });

        $this->app->singleton(ZatcaManager::class, function ($app): ZatcaManager {
            return new ZatcaManager(
                $app->make(TenantResolver::class),
                $app->make(TenantConfigRepository::class),
                $app->make(QrCodeGenerator::class),
                $app->make(XmlGenerator::class),
                $app->make(InvoiceSigner::class),
                $app->make(InvoiceValidator::class),
                $app->make(ZatcaLogger::class),
                $app->make(InvoiceNormalizer::class),
                $app->make(SubmissionPipeline::class),
                $app->make(HashGenerator::class),
                $app->make(OnboardingClient::class),
                $app->make(Phase2QrCodeGenerator::class),
                $app->make(CsrGenerator::class)
            );
        });

        $this->app->singleton(TenantOnboardingFlow::class, function ($app): TenantOnboardingFlow {
            return new TenantOnboardingFlow(
                $app->make(ZatcaManager::class),
                $app->make(SimpleWorkspaceManager::class)
            );
        });

        $this->app->singleton(TenantInvoiceSubmissionFlow::class, function ($app): TenantInvoiceSubmissionFlow {
            return new TenantInvoiceSubmissionFlow(
                $app->make(ZatcaManager::class)
            );
        });

        $this->app->singleton(TenantCredentialHealthChecker::class, function ($app): TenantCredentialHealthChecker {
            return new TenantCredentialHealthChecker(
                $app->make(CertificateLoader::class),
                $app['config']
            );
        });

        $this->app->singleton(CredentialRotationService::class, function ($app): CredentialRotationService {
            return new CredentialRotationService(
                $app['config'],
                $app['db']
            );
        });

        $this->app->singleton(TenantNotificationHookDispatcher::class, function ($app): TenantNotificationHookDispatcher {
            return new TenantNotificationHookDispatcher(
                $app->make(\Illuminate\Http\Client\Factory::class)
            );
        });

        $this->app->alias(ZatcaManager::class, 'zatca');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'zatca');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'zatca');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ((bool) config('zatca.onboarding.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

        if ((bool) config('zatca.onboarding.dashboard.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        Event::listen(TenantCredentialHealthAlertDetected::class, DispatchTenantHealthAlertToHooks::class);
        Event::listen(TenantInvoiceSubmissionAlertDetected::class, DispatchTenantInvoiceAlertToHooks::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (! (bool) config('zatca.health.monitor.enabled', false)) {
                return;
            }

            $command = implode(' ', array_filter([
                'zatca:tenant-health-monitor',
                '--minimum-severity=' . (string) config('zatca.health.monitor.minimum_severity', 'warning'),
                '--fail-on=' . (string) config('zatca.health.monitor.fail_on', 'error'),
                (bool) config('zatca.health.monitor.show_issues', false) ? '--show-issues' : null,
            ]));

            $schedule->command($command)
                ->cron((string) config('zatca.health.monitor.cron', '0 * * * *'))
                ->withoutOverlapping();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/zatca.php' => config_path('zatca.php'),
        ], self::CONFIG_TAG);

        $this->publishes([
            __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/zatca'),
        ], self::LANG_TAG);

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], self::MIGRATIONS_TAG);

        $this->publishes([
            __DIR__ . '/../config/zatca.php' => config_path('zatca.php'),
            __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/zatca'),
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], self::INSTALL_TAG);

        if (! (bool) config('zatca.commands.enabled', true)) {
            return;
        }

        $this->commands([
            ComplianceCheckSampleCommand::class,
            CheckTenantHealthCommand::class,
            GenerateCsrCommand::class,
            InstallCommand::class,
            MonitorTenantHealthCommand::class,
            RequestComplianceCsidCommand::class,
            RequestProductionCsidCommand::class,
            RotateTenantCredentialsCommand::class,
            SdkValidateCommand::class,
            SubmitSampleInvoiceCommand::class,
        ]);
    }
}
