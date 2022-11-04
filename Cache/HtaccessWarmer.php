<?php

namespace Well\Known\Cache;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Well\Known\Factory\WellKnownFactory;

class HtaccessWarmer implements CacheWarmerInterface
{
    public function __construct(WellKnownFactory $wellKnownFactory)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->wellKnownFactory   = $wellKnownFactory;
    }

    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        $security = $this->wellKnownFactory->htaccess();
        if(!$security) return [];

        if($this->shellVerbosity > 0 && php_sapi_name() == "cli")
            echo " // Warming up cache... Well Known Bundle.. .htaccess".PHP_EOL.PHP_EOL;

        return [$security];
    }
}