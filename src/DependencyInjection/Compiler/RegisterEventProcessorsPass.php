<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\DependencyInjection\Compiler;

use Druidvav\SentryExtensionBundle\Sentry\EventProcessorRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RegisterEventProcessorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(EventProcessorRegistry::class)) {
            return;
        }

        $processors = [];
        foreach (array_keys($container->findTaggedServiceIds('sentry.event_processor')) as $id) {
            $processors[] = new Reference($id);
        }

        $container->getDefinition(EventProcessorRegistry::class)
            ->setArgument('$processors', $processors);
    }
}
