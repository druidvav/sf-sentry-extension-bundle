<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\DependencyInjection;

use Druidvav\SentryExtensionBundle\EventListener\SentryExceptionListener;
use Druidvav\SentryExtensionBundle\EventListener\SentryKernelExceptionListener;
use Sentry\State\HubInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SentryExtensionExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register(SentryExceptionListener::class)
            ->setArgument('$hub', new Reference(HubInterface::class))
            ->setPublic(false);

        $container->register(SentryKernelExceptionListener::class)
            ->setArgument('$inner', new Reference(SentryExceptionListener::class))
            ->setPublic(false)
            ->addTag('kernel.event_listener', [
                'event'    => 'kernel.exception',
                'method'   => 'onKernelException',
                'priority' => 2048,
            ]);
    }
}
