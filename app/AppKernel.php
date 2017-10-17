<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new AppBundle\AppBundle(),
            new UKMNorge\DeltaDesignBundle\UKMDeltaDesignBundle(),
            new UKMNorge\DesignBundle\UKMDesignBundle(),
            new UKMNorge\DeltaBundle\UKMDeltaBundle(),
			new FOS\UserBundle\FOSUserBundle(),
            new UKMNorge\UserBundle\UKMUserBundle(),
            new UKMNorge\SMSBundle\UKMSMSBundle(),
            new UKMNorge\APIBundle\UKMAPIBundle(),
        );

        if (in_array($this->getEnvironment(), array('dev', 'test'))) {
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }
    
	public function getCacheDir()
    {
        return '/tmp/symfony/ukmdelta/cache';
    }
	public function getLogDir()
    {
        return '/tmp/symfony/ukmdelta/log';
    }
}
