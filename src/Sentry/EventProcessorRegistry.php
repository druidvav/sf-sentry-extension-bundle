<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Sentry;

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
            if (method_exists(\Sentry\State\Scope::class, 'addGlobalEventProcessor')) {
                // sentry-php v3 / sentry-symfony ^4.0
                \Sentry\State\Scope::addGlobalEventProcessor($processor);
            } elseif (function_exists('\Sentry\addGlobalEventProcessor')) {
                // sentry-php v4 / sentry-symfony ^5.0
                \Sentry\addGlobalEventProcessor($processor);
            }
        }
    }
}
