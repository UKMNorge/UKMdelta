<?php
// src/UKMNorge/UserBundle/DependencyInjection/Compiler/OverrideServiceCompilerPass.php

namespace UKMNorge\UserBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OverrideServiceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('fos_user.listener.email_confirmation');
        $definition->setClass('UKMNorge\UserBundle\EventListener\EmailConfirmationListener');
    }
}