<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'zatca:install {--force : Overwrite any existing published files}';

    protected $description = 'Publish ZATCA configuration and language files.';

    public function handle(): int
    {
        $parameters = ['--tag' => 'zatca-install'];

        if ((bool) $this->option('force')) {
            $parameters['--force'] = true;
        }

        $this->call('vendor:publish', $parameters);

        $this->components->info((string) trans('zatca::commands.install_complete'));

        return self::SUCCESS;
    }
}
