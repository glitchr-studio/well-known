<?php

namespace Tests\Well\Known\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Well\Known\Factory\WellKnownFactory;

/**
 * public/robots.txt (security.txt, ads.txt...), linked to the file the
 * factory wrote under .well-known when alias_to_public is on: made, replaced
 * when something else is in its place, left alone when it is right - and made
 * by several processes at once without one stopping another: an
 * application's web, cron and worker containers warm their caches together
 * as they start, and the slower one met "unlink(...): No such file or
 * directory" (the link another had just removed) or "symlink(): File exists",
 * and its container stopped.
 */
class CreateSymbolinkTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/well-known-link-' . uniqid();
        mkdir($this->projectDir . '/public/.well-known', 0777, true);
        mkdir($this->projectDir . '/public/other', 0777, true);
        file_put_contents($this->projectDir . '/public/.well-known/robots.txt', "User-agent: *\n");
        file_put_contents($this->projectDir . '/public/other/robots.txt', "User-agent: other\n");
    }

    protected function tearDown(): void
    {
        $this->remove($this->projectDir);
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }
        rmdir($path);
    }

    private static function factory(string $projectDir): WellKnownFactory
    {
        return new WellKnownFactory(new ParameterBag([
            'well_known.enable' => true,
            'well_known.location_uri' => '/.well-known',
            'well_known.basedir_warning' => true,
            'well_known.alias_to_public' => true,
            'well_known.override_existing' => true,
            'kernel.project_dir' => $projectDir,
        ]));
    }

    private function link(): string
    {
        return $this->projectDir . '/public/robots.txt';
    }

    public function testTheLinkIsMade(): void
    {
        self::factory($this->projectDir)->createSymbolink($this->projectDir . '/public/.well-known/robots.txt');

        $this->assertTrue(is_link($this->link()));
        $this->assertSame(".well-known/robots.txt", readlink($this->link()));
        $this->assertSame("User-agent: *\n", file_get_contents($this->link()));
    }

    public function testARightLinkIsLeftAlone(): void
    {
        symlink('.well-known/robots.txt', $this->link());
        $inode = lstat($this->link())['ino'];

        self::factory($this->projectDir)->createSymbolink($this->projectDir . '/public/.well-known/robots.txt');

        $this->assertSame($inode, lstat($this->link())['ino'], 'the same link, not made again');
    }

    public function testAnotherLinkAFileOrAnEmptyDirectoryInItsPlaceIsReplaced(): void
    {
        $factory = self::factory($this->projectDir);
        foreach (['link' => fn () => symlink('other/robots.txt', $this->link()), 'dangling link' => fn () => symlink('nowhere/robots.txt', $this->link()), 'file' => fn () => file_put_contents($this->link(), 'old'), 'empty directory' => fn () => mkdir($this->link())] as $what => $put) {
            $this->remove($this->link());
            $put();
            $factory->createSymbolink($this->projectDir . '/public/.well-known/robots.txt');
            $this->assertTrue(is_link($this->link()), $what);
            $this->assertSame(".well-known/robots.txt", readlink($this->link()), $what);
        }
        $this->assertSame(['.well-known', 'other', 'robots.txt'], array_values(array_diff(scandir($this->projectDir . '/public'), ['.', '..'])), 'no temporary link left behind');
    }

    /**
     * Six processes, each linking public/robots.txt two hundred times, now
     * to one file, now to the other: none fails, and the link names one of
     * them.
     */
    public function testSeveralProcessesAtOnce(): void
    {
        if (!\function_exists('proc_open')) {
            $this->markTestSkipped('proc_open() is disabled.');
        }

        $autoload = \dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2) . '/autoload.php';
        $script = $this->projectDir . '/link.php';
        file_put_contents($script, '<?php
            require ' . var_export($autoload, true) . ';
            set_error_handler(function (int $severity, string $message) { throw new ErrorException($message, 0, $severity); });
            $factory = new Well\Known\Factory\WellKnownFactory(new Symfony\Component\DependencyInjection\ParameterBag\ParameterBag([
                "well_known.enable" => true, "well_known.location_uri" => "/.well-known", "well_known.basedir_warning" => true,
                "well_known.alias_to_public" => true, "well_known.override_existing" => true, "kernel.project_dir" => ' . var_export($this->projectDir, true) . ',
            ]));
            for ($i = 0; $i < 200; ++$i) {
                $factory->createSymbolink(' . var_export($this->projectDir, true) . ' . ($i % 2 ? "/public/other/robots.txt" : "/public/.well-known/robots.txt"));
            }
        ');

        $processes = $outputs = [];
        for ($n = 0; $n < 6; ++$n) {
            $processes[] = proc_open([\PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $outputs[$n] = $pipes;
        }
        $failures = [];
        foreach ($processes as $n => $process) {
            $out = stream_get_contents($outputs[$n][1]) . stream_get_contents($outputs[$n][2]);
            $code = proc_close($process);
            if (0 !== $code) {
                $failures[] = 'process ' . $n . ' (' . $code . '): ' . trim(substr($out, 0, 300));
            }
        }

        $this->assertSame([], $failures);
        $this->assertContains(readlink($this->link()), ['.well-known/robots.txt', 'other/robots.txt']);
        $this->assertSame(['.well-known', 'other', 'robots.txt'], array_values(array_diff(scandir($this->projectDir . '/public'), ['.', '..'])), 'no temporary link left behind');
    }
}
