<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class SdkValidateCommand extends Command
{
    protected $signature = 'zatca:sdk-validate
        {invoice : Path to the final signed invoice XML}
        {--sdk= : Official ZATCA SDK root path}
        {--cli= : fatooraNet.exe or fatooraNet.dll path}
        {--certificate= : Certificate PEM path}
        {--pih= : PIH text file path}';

    protected $description = 'Validate a signed invoice XML using the official ZATCA SDK CLI.';

    public function handle(): int
    {
        try {
            $sdkPath = $this->resolvePath($this->stringOption('sdk') ?? $this->stringConfig('zatca.sdk.path'));
            $cliPath = $this->resolveRequiredFile(
                $this->stringOption('cli')
                    ?? $this->stringConfig('zatca.sdk.cli_path')
                    ?? $this->defaultCliPath($sdkPath),
                'SDK CLI'
            );
            $invoicePath = $this->resolveRequiredFile((string) $this->argument('invoice'), 'Invoice XML');
            $certificatePath = $this->resolveRequiredFile(
                $this->stringOption('certificate')
                    ?? $this->stringConfig('zatca.default_tenant.certificates.certificate_path')
                    ?? $this->defaultCertificatePath($sdkPath),
                'Certificate'
            );
            $pihPath = $this->resolveRequiredFile(
                $this->stringOption('pih')
                    ?? $this->stringConfig('zatca.sdk.resources.pih_path')
                    ?? $this->defaultPihPath($sdkPath),
                'PIH'
            );

            $command = $this->buildCommand($cliPath, $invoicePath, $certificatePath, $pihPath);

            $this->components->info('Running official ZATCA SDK validator.');
            $this->line('Invoice: ' . $invoicePath);
            $this->line('CLI: ' . $cliPath);
            $this->newLine();

            $result = $this->runSdkCommand($command, $this->workingDirectory($cliPath));
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result['stdout'] !== '') {
            $this->line(rtrim($result['stdout']));
        }

        if ($result['stderr'] !== '') {
            $this->warn(rtrim($result['stderr']));
        }

        if ($result['exit_code'] !== 0) {
            $this->components->error('SDK validation failed with exit code ' . $result['exit_code'] . '.');

            return self::FAILURE;
        }

        if ($this->sdkReportedFailure($result['stdout'], $result['stderr'])) {
            $this->components->error('SDK validation reported invoice errors.');

            return self::FAILURE;
        }

        $this->components->info('SDK validation completed successfully.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function buildCommand(string $cliPath, string $invoicePath, string $certificatePath, string $pihPath): array
    {
        $command = str_ends_with(strtolower($cliPath), '.dll')
            ? [$this->dotnetBinary(), $cliPath]
            : [$cliPath];

        return [
            ...$command,
            'validate',
            '-invoice',
            $invoicePath,
            '-certificate',
            $certificatePath,
            '-pih',
            $pihPath,
        ];
    }

    private function dotnetBinary(): string
    {
        return $this->stringConfig('zatca.sdk.dotnet_binary') ?? 'dotnet';
    }

    /**
     * @param array<int, string> $command
     *
     * @return array{stdout: string, stderr: string, exit_code: int}
     */
    private function runSdkCommand(array $command, string $workingDirectory): array
    {
        $process = proc_open(
            implode(' ', array_map(static fn (string $argument): string => escapeshellarg($argument), $command)),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start the official ZATCA SDK validator process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
            'exit_code' => proc_close($process),
        ];
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function stringConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function resolveRequiredFile(?string $path, string $label): string
    {
        $resolvedPath = $this->resolvePath($path);

        if ($resolvedPath === null || ! is_file($resolvedPath)) {
            throw new RuntimeException($label . ' file was not found at: ' . ($path ?: '[not configured]'));
        }

        return $resolvedPath;
    }

    private function resolvePath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (! $this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        return realpath($path) ?: $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private function defaultCliPath(?string $sdkPath): ?string
    {
        if ($sdkPath === null) {
            return null;
        }

        $directory = $sdkPath . DIRECTORY_SEPARATOR . 'Lib' . DIRECTORY_SEPARATOR . 'Dot-Net8' . DIRECTORY_SEPARATOR . 'Test';
        $candidates = PHP_OS_FAMILY === 'Windows'
            ? [$directory . DIRECTORY_SEPARATOR . 'fatooraNet.exe', $directory . DIRECTORY_SEPARATOR . 'fatooraNet.dll']
            : [$directory . DIRECTORY_SEPARATOR . 'fatooraNet.dll', $directory . DIRECTORY_SEPARATOR . 'fatooraNet.exe'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }

    private function defaultCertificatePath(?string $sdkPath): ?string
    {
        return $sdkPath === null
            ? null
            : $sdkPath . DIRECTORY_SEPARATOR . 'Data' . DIRECTORY_SEPARATOR . 'Certificates' . DIRECTORY_SEPARATOR . 'cert.pem';
    }

    private function defaultPihPath(?string $sdkPath): ?string
    {
        return $sdkPath === null
            ? null
            : $sdkPath . DIRECTORY_SEPARATOR . 'Data' . DIRECTORY_SEPARATOR . 'PIH' . DIRECTORY_SEPARATOR . 'pih.txt';
    }

    private function workingDirectory(string $cliPath): string
    {
        $directory = dirname($cliPath);

        return is_dir($directory) ? $directory : base_path();
    }

    private function sdkReportedFailure(string $stdout, string $stderr): bool
    {
        return str_contains($stdout, 'Overall status [Failed]')
            || str_contains($stderr, 'Overall status [Failed]');
    }
}
