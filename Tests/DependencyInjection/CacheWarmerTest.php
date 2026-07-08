<?php

namespace Tests\Well\Known\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Well\Known\DependencyInjection\CacheWarmer;
use Well\Known\Factory\WellKnownFactory;

class CacheWarmerTest extends TestCase
{
    public function testIsOptional(): void
    {
        $factory = $this->createMock(WellKnownFactory::class);

        $this->assertTrue((new CacheWarmer($factory))->isOptional());
    }

    public function testWarmUpCallsEveryGeneratorAndReturnsOnlyTheProducedFiles(): void
    {
        $factory = $this->createMock(WellKnownFactory::class);
        $factory->method('robots')->willReturn('/public/.well-known/robots.txt');
        $factory->method('security')->willReturn(null);
        $factory->method('humans')->willReturn('/public/.well-known/humans.txt');
        $factory->method('ads')->willReturn(null);
        $factory->method('htaccess')->willReturn(null);

        $result = (new CacheWarmer($factory))->warmUp('/tmp/cache');

        $this->assertSame([
            '/public/.well-known/robots.txt',
            '/public/.well-known/humans.txt',
        ], array_values($result));
    }
}
