<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $config) {

    $services = $config->services();

    $services->defaults()
        ->public(false)
        ->autowire()
        ->autoconfigure();

    $services
        ->set(Well\Known\Factory\WellKnownFactory::class)
        ->public(true);

    $services
        ->set(Well\Known\DependencyInjection\CacheWarmer::class)
        ->public(true)
        ->tag('kernel.cache_warmer');
};