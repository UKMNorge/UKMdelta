<?php

namespace UKMNorge\UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use UKMNorge\UserBundle\DependencyInjection\Compiler\OverrideServiceCompilerPass;

class UKMUserBundle extends Bundle
{
    public function getParent()
    {
        return 'FOSUserBundle';
    }
    
	public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new OverrideServiceCompilerPass());
    }
}
