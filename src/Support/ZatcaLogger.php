<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Support;

use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use Throwable;

class ZatcaLogger
{
    public function __construct(
        protected LogManager $logManager
    ) {
    }

    public function debug(string $message, array $context = []): void
    {
        if (! $this->isEnabled() || ! $this->isDebug()) {
            return;
        }

        $this->logger()->debug($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->logger()->info($message, $context);
    }

    public function error(string $message, array $context = [], ?Throwable $exception = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if ($exception instanceof Throwable) {
            $context['exception'] = $exception;
        }

        $this->logger()->error($message, $context);
    }

    protected function logger(): LoggerInterface
    {
        $channel = config('zatca.logging.channel');

        return is_string($channel) && $channel !== ''
            ? $this->logManager->channel($channel)
            : $this->logManager;
    }

    protected function isEnabled(): bool
    {
        return (bool) config('zatca.logging.enabled', true);
    }

    protected function isDebug(): bool
    {
        return (bool) config('zatca.debug', true);
    }
}
