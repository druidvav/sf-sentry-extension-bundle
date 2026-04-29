<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\EventListener;

use Druidvav\SentryExtensionBundle\Sentry\EventProcessorRegistry;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class SentryProcessorsBootListener
{
    private bool $registered = false;

    public function __construct(private readonly EventProcessorRegistry $registry)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $this->boot();
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->boot();
    }

    private function boot(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;
        $this->registry->register();
    }
}
