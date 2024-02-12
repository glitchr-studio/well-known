<?php

namespace Well\Known\DependencyInjection;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Well\Known\Factory\WellKnownFactory;

/**
 *
 */
class CacheWarmer implements CacheWarmerInterface
{
    /**
     * @var string
     */
    protected string $shellVerbosity;

    /**
     * @var WellKnownFactory
     */
    protected WellKnownFactory $wellKnownFactory;

    public function __construct(WellKnownFactory $wellKnownFactory)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->wellKnownFactory = $wellKnownFactory;
    }

    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @param $cacheDir
     * @return array|string[]
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->shellVerbosity > 0 && php_sapi_name() == "cli") {
            echo " // Warming up cache... Well Known Bundle.. ";
        }

        $robots = $this->wellKnownFactory->robots();
        if ($this->shellVerbosity > 0 && php_sapi_name() == "cli" && $robots) {
            echo "robots.txt.. ";
        }

        $security = $this->wellKnownFactory->security();
        if ($this->shellVerbosity > 0 && php_sapi_name() == "cli" && $security) {
            echo "security.txt.. ";
        }

        $humans = $this->wellKnownFactory->humans();
        if ($this->shellVerbosity > 0 && php_sapi_name() == "cli" && $humans) {
            echo "humans.txt.. ";
        }

        $ads = $this->wellKnownFactory->ads();
        if ($this->shellVerbosity > 0 && php_sapi_name() == "cli" && $ads) {
            echo "ads.txt.. ";
        }

        $htaccess = $this->wellKnownFactory->htaccess();
        if ($this->shellVerbosity > 0 && php_sapi_name() == "cli" && $htaccess) {
            echo ".htaccess.. ";
        }

        if ($this->shellVerbosity > 0 && php_sapi_name() == "cli") {
            echo PHP_EOL . PHP_EOL;
        }

        return array_filter([$security, $robots, $humans, $ads, $htaccess]);
    }
}
