<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests\Feature;

use Illuminate\Console\Command;
use Maaz\LaravelZatca\Tests\TestCase;

class SdkValidateCommandTest extends TestCase
{
    public function test_it_fails_when_invoice_file_is_missing(): void
    {
        $this->artisan('zatca:sdk-validate', [
            'invoice' => 'missing-invoice.xml',
        ])->assertExitCode(Command::FAILURE);
    }

    public function test_it_can_bridge_to_an_sdk_cli_binary(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-sdk-' . uniqid();

        mkdir($directory, 0777, true);

        $invoicePath = $directory . DIRECTORY_SEPARATOR . 'invoice.xml';
        $certificatePath = $directory . DIRECTORY_SEPARATOR . 'cert.pem';
        $pihPath = $directory . DIRECTORY_SEPARATOR . 'pih.txt';
        $cliPath = $directory . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'fatooraNet.bat' : 'fatooraNet');

        file_put_contents($invoicePath, '<Invoice/>');
        file_put_contents($certificatePath, 'certificate');
        file_put_contents($pihPath, 'pih');
        file_put_contents($cliPath, $this->fakeCliScript());

        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($cliPath, 0755);
        }

        try {
            $this->artisan('zatca:sdk-validate', [
                'invoice' => $invoicePath,
                '--cli' => $cliPath,
                '--certificate' => $certificatePath,
                '--pih' => $pihPath,
            ])->assertExitCode(Command::SUCCESS);
        } finally {
            @unlink($cliPath);
            @unlink($pihPath);
            @unlink($certificatePath);
            @unlink($invoicePath);
            @rmdir($directory);
        }
    }

    public function test_it_fails_when_sdk_reports_a_validation_failure_with_zero_exit_code(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-zatca-sdk-' . uniqid();

        mkdir($directory, 0777, true);

        $invoicePath = $directory . DIRECTORY_SEPARATOR . 'invoice.xml';
        $certificatePath = $directory . DIRECTORY_SEPARATOR . 'cert.pem';
        $pihPath = $directory . DIRECTORY_SEPARATOR . 'pih.txt';
        $cliPath = $directory . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'fatooraNet.bat' : 'fatooraNet');

        file_put_contents($invoicePath, '<Invoice/>');
        file_put_contents($certificatePath, 'certificate');
        file_put_contents($pihPath, 'pih');
        file_put_contents($cliPath, $this->failingCliScript());

        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($cliPath, 0755);
        }

        try {
            $this->artisan('zatca:sdk-validate', [
                'invoice' => $invoicePath,
                '--cli' => $cliPath,
                '--certificate' => $certificatePath,
                '--pih' => $pihPath,
            ])->assertExitCode(Command::FAILURE);
        } finally {
            @unlink($cliPath);
            @unlink($pihPath);
            @unlink($certificatePath);
            @unlink($invoicePath);
            @rmdir($directory);
        }
    }

    private function fakeCliScript(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return "@echo off\r\necho SDK OK\r\nexit /B 0\r\n";
        }

        return "#!/usr/bin/env sh\nprintf 'SDK OK\\n'\nexit 0\n";
    }

    private function failingCliScript(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return "@echo off\r\necho Overall status [Failed]\r\nexit /B 0\r\n";
        }

        return "#!/usr/bin/env sh\nprintf 'Overall status [Failed]\\n'\nexit 0\n";
    }
}
