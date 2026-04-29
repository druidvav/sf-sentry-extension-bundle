<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\EventListener;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class SentryKernelExceptionListener
{
    private SentryExceptionListener $inner;

    public function __construct(SentryExceptionListener $inner)
    {
        $this->inner = $inner;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $this->inner->onException($event->getThrowable());
    }
}
