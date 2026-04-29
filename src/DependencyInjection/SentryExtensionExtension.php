<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\DependencyInjection;

use Druidvav\SentryExtensionBundle\EventListener\SentryProcessorsBootListener;
use Druidvav\SentryExtensionBundle\Sentry\EventProcessorRegistry;
use Druidvav\SentryExtensionBundle\Sentry\SentryExceptionContextProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SentryExtensionExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register(SentryExceptionContextProcessor::class)
            ->setPublic(false)
            ->addTag('sentry.event_processor');

        // $processors will be populated by RegisterEventProcessorsPass
        $container->register(EventProcessorRegistry::class)
            ->setArgument('$processors', [])
            ->setPublic(false);

        $container->register(SentryProcessorsBootListener::class)
            ->setArgument('$registry', new Reference(EventProcessorRegistry::class))
            ->setPublic(false)
            ->addTag('kernel.event_listener', [
                'event'    => 'kernel.request',
                'method'   => 'onKernelRequest',
                'priority' => 9999,
            ])
            ->addTag('kernel.event_listener', [
                'event'    => 'console.command',
                'method'   => 'onConsoleCommand',
                'priority' => 9999,
            ]);
    }
}
