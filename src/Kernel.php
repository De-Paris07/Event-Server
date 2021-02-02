<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles()
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new \Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle(),
            new \Symfony\Bundle\MakerBundle\MakerBundle(),
            new \Leezy\PheanstalkBundle\LeezyPheanstalkBundle(),
            new \Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle(),
            new \Sentry\SentryBundle\SentryBundle(),
            new \Symfony\Bundle\MonologBundle\MonologBundle,
            new \Snc\RedisBundle\SncRedisBundle(),
        ];
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/../config/config.yml');
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        // load the annotation routes
        $routes->import(__DIR__.'/../src/Controller/', '/', 'annotation');
    }

    // optional, to use the standard Symfony cache directory
    public function getCacheDir()
    {
        return __DIR__.'/../var/cache/'.$this->getEnvironment();
    }

    // optional, to use the standard Symfony logs directory
    public function getLogDir()
    {
        return __DIR__.'/../var/log';
    }
}
