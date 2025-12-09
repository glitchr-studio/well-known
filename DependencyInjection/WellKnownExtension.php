<?php

namespace Well\Known\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class WellKnownExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // NEW PHP-based services config loader
        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.php');   // Replaces services.xml

        // ---- Bundle configuration remains unchanged ----
        $processor      = new Processor();
        $configuration  = new Configuration();
        $config         = $processor->processConfiguration($configuration, $configs);

        $rootName = $configuration->getTreeBuilder()
            ->getRootNode()
            ->getNode()
            ->getName();

        $this->setConfiguration($container, $config, $rootName);
    }

    public function setConfiguration(ContainerBuilder $container, array $config, string $globalKey = ''): void
    {
        foreach ($config as $key => $value) {
            $fullKey = $globalKey ? $globalKey . '.' . $key : $key;

            if (is_array($value)) {
                $this->setConfiguration($container, $value, $fullKey);
            } else {
                $container->setParameter($fullKey, $value);
            }
        }
    }
}