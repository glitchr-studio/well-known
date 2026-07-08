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

            if (is_array($value) && !$this->isList($value)) {
                $this->setConfiguration($container, $value, $fullKey);
            } else {
                $container->setParameter($fullKey, $value);
            }
        }
    }

    /**
     * A nested config *section* (e.g. security_txt) needs flattening into
     * its own dot-notation keys, but a plain list value (contacts,
     * preferred_languages, ads_txt entries, robots_txt entries) needs to be
     * stored as a single parameter, not exploded into "...key.0",
     * "...key.1" sub-parameters that nothing ever reads back under their
     * own aggregate key.
     */
    private function isList(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }
}