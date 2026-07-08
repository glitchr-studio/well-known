<?php

namespace Tests\Well\Known\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Well\Known\DependencyInjection\Configuration;

class ConfigurationTest extends TestCase
{
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }

    public function testDefaultsWithNoConfigurationAtAll(): void
    {
        $config = $this->process([]);

        $this->assertTrue($config['enable']);
        $this->assertTrue($config['basedir_warning']);
        $this->assertTrue($config['alias_to_public']);
        $this->assertTrue($config['override_existing']);
        $this->assertSame('/.well-known', $config['location_uri']);
        $this->assertSame('~/.gnupg', $config['gnupg']['directory']);
        $this->assertNull($config['resources']['humans_txt']);
        $this->assertSame([], $config['resources']['ads_txt']);
        $this->assertSame([], $config['resources']['robots_txt']);
        $this->assertNull($config['resources']['security_txt']['canonical']);
        $this->assertNull($config['resources']['security_txt']['encryption']);
        $this->assertNull($config['resources']['security_txt']['expires']);
    }

    public function testOverridingScalarAndArrayValues(): void
    {
        $config = $this->process([[
            'enable' => false,
            'location_uri' => '/custom-well-known',
            'resources' => [
                'humans_txt' => '/path/to/humans.txt',
                'security_txt' => [
                    'canonical' => 'https://example.com/.well-known/security.txt',
                    'contacts' => ['mailto:security@example.com'],
                ],
            ],
        ]]);

        $this->assertFalse($config['enable']);
        $this->assertSame('/custom-well-known', $config['location_uri']);
        $this->assertSame('/path/to/humans.txt', $config['resources']['humans_txt']);
        $this->assertSame('https://example.com/.well-known/security.txt', $config['resources']['security_txt']['canonical']);
        $this->assertSame(['mailto:security@example.com'], $config['resources']['security_txt']['contacts']);
    }

    public function testRobotsTxtEntryStructure(): void
    {
        $config = $this->process([[
            'resources' => [
                'robots_txt' => [
                    [
                        'user_agent' => ['*'],
                        'disallow' => ['/admin'],
                        'allow' => ['/'],
                        'sitemap' => ['/sitemap.xml'],
                    ],
                ],
            ],
        ]]);

        $this->assertSame([
            'user_agent' => ['*'],
            'disallow' => ['/admin'],
            'allow' => ['/'],
            'sitemap' => ['/sitemap.xml'],
        ], $config['resources']['robots_txt'][0]);
    }

    public function testGetTreeBuilderReturnsTheBuilderCreatedByGetConfigTreeBuilder(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        $this->assertSame($treeBuilder, $configuration->getTreeBuilder());
    }
}
