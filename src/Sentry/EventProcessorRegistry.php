<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Sentry;

use Sentry\State\Scope;

/**
 * Receives all services tagged sentry.event_processor and registers them
 * as global Sentry event processors. Instantiated early via boot listener.
 */
class EventProcessorRegistry
{
    /** @param callable[] $processors */
    public function __construct(private readonly array $processors)
    {
    }

    public function register(): void
    {
        foreach ($this->processors as $processor) {
            Scope::addGlobalEventProcessor($processor);
        }
    }
}
