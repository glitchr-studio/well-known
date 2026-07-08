<?php

namespace Tests\Well\Known\Factory;

use DateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Well\Known\Factory\WellKnownFactory;

class WellKnownFactoryTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/well-known-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function makeFactory(array $params = []): WellKnownFactory
    {
        $defaults = [
            'well_known.enable' => true,
            'well_known.location_uri' => '/.well-known',
            'well_known.basedir_warning' => true,
            'well_known.alias_to_public' => false,
            'well_known.override_existing' => true,
            'kernel.project_dir' => $this->projectDir,
        ];
        $params = array_merge($defaults, $params);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnCallback(fn(string $key) => $params[$key] ?? null);
        $parameterBag->method('has')->willReturnCallback(fn(string $key) => array_key_exists($key, $params));

        return new WellKnownFactory($parameterBag);
    }

    public function testGetPublicDirAndProjectDir(): void
    {
        $factory = $this->makeFactory();

        $this->assertSame($this->projectDir, $factory->getProjectDir());
        $this->assertSame($this->projectDir . '/public', $factory->getPublicDir());
    }

    public function testFormatBuildsAMailtoLinkAndDeduplicatesAnExistingPrefix(): void
    {
        $factory = $this->makeFactory();

        $this->assertSame('mailto: security@example.com', $factory->format('security@example.com'));
        $this->assertSame('mailto: security@example.com', $factory->format('mailto:security@example.com'));
    }

    public function testFormatReturnsAFullUrlUnchanged(): void
    {
        $factory = $this->makeFactory();

        $this->assertSame('https://example.com/security.txt', $factory->format('https://example.com/security.txt'));
    }

    public function testFormatWithALeadingSlashIsRootedAtThePublicDir(): void
    {
        $factory = $this->makeFactory();

        $this->assertSame($this->projectDir . '/public/foo.txt', $factory->format('/foo.txt'));
    }

    public function testFormatWithALeadingSlashCanStripAPrefixBackOff(): void
    {
        $factory = $this->makeFactory();

        // Default $stripPrefix is "" (a no-op strip, since StringStripPrefix
        // returns the haystack unchanged for an empty needle) — passing the
        // public dir explicitly is what actually normalizes it back off.
        $this->assertSame('/foo.txt', $factory->format('/foo.txt', $this->projectDir . '/public'));
    }

    public function testFormatWithARelativeNameCreatesTheLocationDirectory(): void
    {
        $factory = $this->makeFactory();
        $expectedDir = $this->projectDir . '/public/.well-known';

        $this->assertSame($expectedDir . '/security.txt', $factory->format('security.txt'));
        $this->assertDirectoryExists($expectedDir);
    }

    public function testIsSafePlaceIsFalseForNull(): void
    {
        $this->assertFalse($this->makeFactory()->isSafePlace(null));
    }

    public function testIsSafePlaceIsTrueForAnyUrl(): void
    {
        $this->assertTrue($this->makeFactory()->isSafePlace('https://example.com/anything'));
    }

    public function testIsSafePlaceIsFalseOutsideThePublicDir(): void
    {
        $this->assertFalse($this->makeFactory()->isSafePlace('/etc/passwd'));
    }

    /**
     * Regression: isSafePlace() used to compute $base via
     * explode("/", $relative, 1)[0] — a limit of 1 means explode() never
     * splits at all (per PHP semantics, "at most 1 element"), so $base was
     * always the entire remaining path, never just its first segment. Since
     * a full path string can never equal a bare directory name like
     * "bundles", the in_array() check never matched and isSafePlace()
     * returned true unconditionally for anything under the public dir —
     * the "don't let generated files land under bundles/assets/storage"
     * safety check was completely inert. WellKnownBundle is actually
     * enabled in the host app (config/bundles.php), so this was live.
     */
    public function testIsSafePlaceIsFalseUnderBundlesAssetsOrStorage(): void
    {
        $factory = $this->makeFactory();
        $publicDir = $this->projectDir . '/public';

        $this->assertFalse($factory->isSafePlace($publicDir . '/bundles/some-bundle/foo.js'));
        $this->assertFalse($factory->isSafePlace($publicDir . '/assets/app.css'));
        $this->assertFalse($factory->isSafePlace($publicDir . '/storage/upload.jpg'));
    }

    public function testIsSafePlaceIsTrueUnderOtherPublicSubdirectories(): void
    {
        $factory = $this->makeFactory();

        $this->assertTrue($factory->isSafePlace($this->projectDir . '/public/.well-known/security.txt'));
    }

    public function testDatetimeReturnsNullForNull(): void
    {
        $this->assertNull($this->makeFactory()->datetime(null));
    }

    public function testDatetimeAcceptsAStrictlyFormattedFutureDate(): void
    {
        $future = (new DateTime('+5 years'))->format(\DateTimeInterface::RFC3339);

        $this->assertSame($future, $this->makeFactory()->datetime($future));
    }

    public function testDatetimeRejectsAStrictlyFormattedPastDate(): void
    {
        $past = (new DateTime('-5 years'))->format(\DateTimeInterface::RFC3339);

        $this->assertNull($this->makeFactory()->datetime($past));
    }

    /**
     * The bundle's own Configuration documents "expires" as accepting
     * "either a datetime using RFC3999 format or modifier (e.g. +1y)", but
     * the modifier branch is dead: $now->modify($value) computes a real
     * future DateTime, but the final check requires that datetime's
     * formatted string to equal the ORIGINAL input ("+1 year"), which a
     * real formatted date can never textually match. Every relative
     * modifier input — valid or not — falls through to null. Documented
     * here as a known gap, not fixed: the correct behavior (should a valid
     * modifier be accepted and formatted, distinct from an invalid one)
     * is a product decision, not a mechanical bug fix.
     */
    public function testDatetimeModifierSyntaxNeverActuallyResolves(): void
    {
        $this->assertNull($this->makeFactory()->datetime('+1 year'));
    }

    public function testSecurityReturnsNullWhenDisabled(): void
    {
        $factory = $this->makeFactory(['well_known.enable' => false]);

        $this->assertNull($factory->security());
    }

    public function testSecurityWritesTheConfiguredFieldsInOrder(): void
    {
        $factory = $this->makeFactory([
            'well_known.resources.security_txt.canonical' => null,
            'well_known.resources.security_txt.encryption' => null,
            'well_known.resources.security_txt.expires' => null,
            'well_known.resources.security_txt.contacts' => ['mailto:security@example.com'],
            'well_known.resources.security_txt.acknowledgements' => null,
            'well_known.resources.security_txt.policy' => null,
            'well_known.resources.security_txt.hiring' => null,
            'well_known.resources.security_txt.preferred_languages' => ['en', 'fr'],
        ]);

        $fname = $factory->security();

        $this->assertNotNull($fname);
        $this->assertFileExists($fname);
        $contents = file_get_contents($fname);
        $this->assertStringContainsString('Contact: mailto: security@example.com', $contents);
        $this->assertStringContainsString('Preferred-Languages: en,fr', $contents);
    }

    public function testSecurityDoesNotOverwriteAnExistingFileUnlessOverrideIsEnabled(): void
    {
        $factory = $this->makeFactory([
            'well_known.override_existing' => false,
            'well_known.resources.security_txt.contacts' => ['mailto:security@example.com'],
        ]);

        $expectedPath = $this->projectDir . '/public/.well-known/security.txt';
        mkdir(dirname($expectedPath), 0777, true);
        file_put_contents($expectedPath, 'pre-existing content');

        $this->assertNull($factory->security());
        $this->assertSame('pre-existing content', file_get_contents($expectedPath));
    }

    public function testRobotsBuildsUserAgentAllowDisallowAndSitemapLines(): void
    {
        $factory = $this->makeFactory([
            'well_known.resources.robots_txt' => [[
                'user-agent' => ['*'],
                'allow' => ['/'],
                'disallow' => ['/admin'],
                'sitemap' => ['/sitemap.xml'],
            ]],
        ]);

        $fname = $factory->robots();

        $this->assertNotNull($fname);
        $contents = file_get_contents($fname);
        $this->assertStringContainsString('User-Agent: *', $contents);
        // format($_, publicDir) absolutizes then immediately strips the
        // same public-dir prefix back off — a bare site-relative path in,
        // the same bare path out, which is what a robots.txt URL directive
        // actually needs (not a filesystem path).
        $this->assertStringContainsString('Allow: /', $contents);
        $this->assertStringContainsString('Disallow: /admin', $contents);
        $this->assertStringContainsString('Sitemap: /sitemap.xml', $contents);
    }

    public function testRobotsReturnsNullWhenNoEntriesProduceAnyContent(): void
    {
        $this->assertNull($this->makeFactory()->robots());
    }

    public function testHtaccessRedirectsChangePasswordWhenConfigured(): void
    {
        $factory = $this->makeFactory(['well_known.resources.change_password' => '/account/password']);

        $fname = $factory->htaccess();

        $this->assertNotNull($fname);
        $this->assertStringContainsString('Redirect 301 /.well-known/change-password', file_get_contents($fname));
    }

    public function testHtaccessReturnsNullWithoutAChangePasswordPage(): void
    {
        $this->assertNull($this->makeFactory()->htaccess());
    }

    public function testAdsConcatenatesEntries(): void
    {
        $factory = $this->makeFactory([
            'well_known.resources.ads_txt' => [['google.com', 'pub-1234', 'DIRECT']],
        ]);

        $fname = $factory->ads();

        $this->assertNotNull($fname);
        $this->assertSame('google.com pub-1234 DIRECT', file_get_contents($fname));
    }
}
