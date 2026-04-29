<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle;

use Druidvav\SentryExtensionBundle\DependencyInjection\Compiler\RegisterEventProcessorsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SentryExtensionBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RegisterEventProcessorsPass());
    }
}
