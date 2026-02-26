<?php
declare(strict_types=1);

namespace Tg\FiberServer\DependencyInjection;



use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class FiberServerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // $configuration = new Configuration();
        // $config = $this->processConfiguration($configuration, $configs);


        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('listeners.php');
    }
}