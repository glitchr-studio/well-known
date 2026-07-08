<?php

namespace Tests\Well\Known\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Well\Known\DependencyInjection\Configuration;
use Well\Known\DependencyInjection\WellKnownExtension;

class WellKnownExtensionTest extends TestCase
{
    public function testSetConfigurationFlattensScalarsIntoDotNotationParameters(): void
    {
        $container = new ContainerBuilder();
        (new WellKnownExtension())->setConfiguration($container, [
            'enable' => true,
            'location_uri' => '/.well-known',
        ], 'well_known');

        $this->assertTrue($container->getParameter('well_known.enable'));
        $this->assertSame('/.well-known', $container->getParameter('well_known.location_uri'));
    }

    /**
     * Regression: setConfiguration() used to recurse into EVERY array
     * value, list or associative, with no distinction. A nested config
     * *section* like security_txt correctly flattens into
     * "well_known.resources.security_txt.canonical", etc. — but a plain
     * LIST value (contacts, preferred_languages, ads_txt entries,
     * robots_txt entries) used to ALSO recurse, exploding it into indexed
     * sub-parameters ("...contacts.0", "...contacts.1") instead of storing
     * the list itself under its own key. WellKnownFactory reads these via
     * $parameterBag->has("...contacts") as a guard before get() — since
     * that exact key was never actually set (only its indexed children
     * were), has() was always false and the entire contacts/
     * preferred_languages/ads_txt/robots_txt config surface could never
     * populate through the real DI container. m.beta's own
     * config/packages/well_known.yaml actively configures robots_txt,
     * ads_txt, and security_txt.contacts/preferred_languages — this was
     * live and silently dropping all of it.
     */
    public function testListValuedConfigIsStoredAsASingleAggregateParameter(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'resources' => [
                'security_txt' => [
                    'contacts' => ['mailto:security@example.com'],
                ],
                'robots_txt' => [
                    ['user_agent' => ['*'], 'disallow' => ['/admin']],
                ],
            ],
        ]]);

        $container = new ContainerBuilder();
        (new WellKnownExtension())->setConfiguration($container, $config, 'well_known');

        $this->assertTrue($container->hasParameter('well_known.resources.security_txt.contacts'));
        $this->assertSame(['mailto:security@example.com'], $container->getParameter('well_known.resources.security_txt.contacts'));
        $this->assertFalse($container->hasParameter('well_known.resources.security_txt.contacts.0'));

        $this->assertSame(
            [['user_agent' => ['*'], 'disallow' => ['/admin'], 'allow' => [], 'sitemap' => []]],
            $container->getParameter('well_known.resources.robots_txt')
        );
    }

    public function testEmptyListIsStoredAsAnEmptyArrayParameter(): void
    {
        $container = new ContainerBuilder();
        (new WellKnownExtension())->setConfiguration($container, ['ads_txt' => []], 'well_known.resources');

        $this->assertTrue($container->hasParameter('well_known.resources.ads_txt'));
        $this->assertSame([], $container->getParameter('well_known.resources.ads_txt'));
    }
}
