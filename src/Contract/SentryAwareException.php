<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Contract;

interface SentryAwareException
{
    /**
     * Returns extra data to be attached to the Sentry event context.
     *
     * @return array<string, mixed>
     */
    public function getSentryContext(): array;
}
