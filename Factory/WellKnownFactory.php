<?php

namespace Well\Known\Factory;

use DateTime;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 *
 */
class WellKnownFactory
{
    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var bool
     */
    protected bool $enable;

    /**
     * @var string
     */
    protected string $basedirWarning;

    /**
     * @var string
     */
    protected string $aliasToPublic;

    /**
     * @var string
     */
    protected string $overrideExistingFiles;

    /**
     * @var string
     */
    protected string $publicDir;

    /**
     * @var string
     */
    protected string $locationUri;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;

        $this->filesystem = new Filesystem();
        $this->enable = $this->parameterBag->get("well_known.enable");
        $this->locationUri = $this->parameterBag->get("well_known.location_uri");
        $this->basedirWarning = $this->parameterBag->get("well_known.basedir_warning");
        $this->aliasToPublic = $this->parameterBag->get("well_known.alias_to_public");
        $this->overrideExistingFiles = $this->parameterBag->get("well_known.override_existing");

        $this->publicDir = $this->parameterBag->get('kernel.project_dir') . "/public";
    }

    /**
     * @param string $path
     * @param string|null $stripPrefix
     * @return string|null
     */
    public function format(string $path, ?string $stripPrefix = "")
    {
        if (str_contains($path, "@")) {
            return "mailto: " . str_lstrip(trim($path), "mailto:");
        }
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (str_starts_with($path, "/")) {
            return str_lstrip($this->getPublicDir() . $path, $stripPrefix);
        }

        $dir = $this->getPublicDir() . "/" . str_lstrip($this->locationUri, "/");
        $this->filesystem->mkdir($dir);

        return str_lstrip($dir . "/" . $path, $stripPrefix);
    }

    public function getPublicDir(): string
    {
        return $this->publicDir;
    }

    /**
     * @param $fname
     * @return bool
     */
    public function isSafePlace($fname)
    {
        if ($fname === null) {
            return false;
        }
        if (filter_var($fname, FILTER_VALIDATE_URL)) {
            return true;
        }
        if (str_starts_with($fname, $this->getPublicDir())) {
            $base = explode("/", str_lstrip($fname, $this->getPublicDir()), 1)[0];
            if (!in_array($base, ["bundles", "assets", "storage"])) {
                return true;
            }
        }

        return false;
    }

    public function datetime(DateTime|string|null $value, string $format = DateTimeInterface::RFC3339): ?string
    {
        if ($value === null) {
            return null;
        }

        $now = new DateTime("now");
        $datetime = $value instanceof DateTime ? $value : DateTime::createFromFormat($format, $value);
        if (!$datetime || $datetime->format($format) !== $value) {
            $datetime = $now->modify($value);
        }

        if ($datetime && $datetime->format($format) === $value && $datetime > $now) {
            return $datetime->format($format);
        }

        return null;
    }

    public function security(): ?string
    {
        if (!$this->enable) {
            return null;
        }

        $fname = $this->format("security.txt");
        if ($this->filesystem->exists($fname) && !$this->overrideExistingFiles) {
            return null;
        }

        if (!$this->isSafePlace($fname)) {
            return null;
        }

        $security = "";
        $canonical = $this->parameterBag->get("well_known.resources.security_txt.canonical") ?? null;
        if ($canonical) {
            $security .= "Canonical: " . $this->format($canonical, $this->getPublicDir()) . PHP_EOL . PHP_EOL;
        }

        $encryption = $this->parameterBag->get("well_known.resources.security_txt.encryption") ?? null;
        if ($encryption) {
            $security .= "Encryption: " . $this->format($encryption, $this->getPublicDir()) . PHP_EOL . PHP_EOL;
        }

        $expires = $this->datetime($this->parameterBag->get("well_known.resources.security_txt.expires"));
        if ($expires) {
            $security .= "Expires: " . $expires . PHP_EOL . PHP_EOL;
        }

        $contacts = $this->parameterBag->get("well_known.resources.security_txt.contacts") ?? [];
        foreach ($contacts ?? [] as $contact) {
            $security .= "Contact: " . $this->format($contact, $this->getPublicDir()) . PHP_EOL;
        }
        if (count($contacts)) {
            $security .= PHP_EOL;
        }

        $format = $this->parameterBag->get("well_known.resources.security_txt.acknowledgements");
        if ($format) {
            $security .= "Acknowledgements: " . $this->format($format, $this->getPublicDir()) . PHP_EOL . PHP_EOL;
        }

        $policy = $this->parameterBag->get("well_known.resources.security_txt.policy");
        if ($policy) {
            $security .= "Policy: " . $this->format($policy, $this->getPublicDir()) . PHP_EOL . PHP_EOL;
        }

        $hiring = $this->parameterBag->get("well_known.resources.security_txt.hirting");
        if ($hiring) {
            $security .= "Hiring: " . $this->format($hiring, $this->getPublicDir()) . PHP_EOL . PHP_EOL;
        }

        $preferredLanguages = $this->parameterBag->get("well_known.resources.security_txt.preferred_languages");
        if ($preferredLanguages) {
            $security .= "Preferred-Languages: " . implode(",", $preferredLanguages);
        }

        if ($security) {
            $this->filesystem->dumpFile($fname, $security);
            if ($this->aliasToPublic) {
                $this->createSymbolink($fname);
            }
        }

        return $security ? $fname : null;
    }

    public function createSymbolink(string $fname)
    {
        $publicPath = $this->getPublicDir() . "/" . basename($fname);
        if (is_link($publicPath)) {
            unlink($publicPath);
        } elseif (is_emptydir($publicPath)) {
            rmdir($publicPath);
        } else if(file_exists($publicPath)) {
            exit("Public path \"$publicPath\" already exists but it is not a symlink\n");
        }
	
        symlink($fname, $publicPath);
    }

    public function robots(): ?string
    {
        if (!$this->enable) {
            return null;
        }

        $fname = $this->format("robots.txt");
        if ($this->filesystem->exists($fname) && !$this->overrideExistingFiles) {
            return null;
        }

        if (!$this->isSafePlace($fname)) {
            return null;
        }

        $robots = "";
        $entries = $this->parameterBag->get("well_known.resources.robots_txt") ?? [];
        foreach ($entries as $entry) {
            foreach ($entry["user-agent"] ?? ["*"] as $_) {
                $robots .= "User-Agent: " . $_ . PHP_EOL . PHP_EOL;
            }
            foreach ($entry["allow"] ?? [] as $_) {
                $robots .= "Allow: " . $this->format($_, $this->getPublicDir()) . PHP_EOL . PHP_EOL;
            }
            foreach ($entry["disallow"] ?? [] as $_) {
                $robots .= "Disallow: " . $this->format($_, $this->getPublicDir()) . PHP_EOL . PHP_EOL;
            }
            foreach ($entry["sitemap"] ?? [] as $_) {
                $robots .= "Sitemap: " . $this->format($_, $this->getPublicDir()) . PHP_EOL . PHP_EOL;
            }
        }

        if ($robots) {
            $this->filesystem->dumpFile($fname, $robots);
            if ($this->aliasToPublic) {
                $this->createSymbolink($fname);
            }
        }

        return $robots ? $fname : null;
    }

    public function htaccess(): ?string
    {
        $fname = $this->format(".htaccess");
        if ($this->filesystem->exists($fname) && !$this->overrideExistingFiles) {
            return null;
        }

        if (!$this->isSafePlace($fname)) {
            return null;
        }

        $htaccess = "";

        $format = $this->parameterBag->get("well_known.resources.change_password");
        if ($format) {
            $htaccess .= "Redirect 301 /.well-known/change-password " . $this->format($format, $this->getPublicDir()) . PHP_EOL;
        }

        if ($htaccess) {
            $this->filesystem->dumpFile($fname, $htaccess);
        }

        return $htaccess ? $fname : null;
    }

    public function humans(): ?string
    {
        if (!$this->enable) {
            return null;
        }

        $fname = $this->format("humans.txt");
        if ($this->filesystem->exists($fname) && !$this->overrideExistingFiles) {
            return null;
        }

        if (!$this->isSafePlace($fname)) {
            return null;
        }

        $humansTxt = $this->parameterBag->get("well_known.resources.humans_txt") ?? null;
        if ($humansTxt == null || !file_exists($humansTxt)) {
            return null;
        }

        $humans = file_get_contents($humansTxt);
        if ($humans) {
            $this->filesystem->dumpFile($fname, $humans);
            if ($this->aliasToPublic) {
                $this->createSymbolink($fname);
            }
        }

        return $humans ? $fname : null;
    }

    public function ads(): ?string
    {
        if (!$this->enable) {
            return null;
        }

        $fname = $this->format("ads.txt");
        if ($this->filesystem->exists($fname) && !$this->overrideExistingFiles) {
            return null;
        }

        if (!$this->isSafePlace($fname)) {
            return null;
        }

        $ads = "";
        $entries = $this->parameterBag->get("well_known.resources.ads_txt") ?? [];
        foreach ($entries as $entry) {
            $ads .= implode(" ", $entry);
        }

        if ($ads) {
            $this->filesystem->dumpFile($fname, $ads);
            if ($this->aliasToPublic) {
                $this->createSymbolink($fname);
            }
        }

        return $ads ? $fname : null;
    }
}
