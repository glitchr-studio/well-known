<?php

namespace Well\Known\Factory;

use DateTime;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class WellKnownFactory
{
    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;

        $this->filesystem            = new Filesystem();
        $this->enable                = $this->parameterBag->get("well_known.enable");
        $this->localeUri             = $this->parameterBag->get("well_known.locale_uri");
        $this->basedirWarning        = $this->parameterBag->get("well_known.basedir_warning");
        $this->overrideExistingFiles = $this->parameterBag->get("override_existing");

        $this->publicDir = $this->parameterBag->get('kernel.project_dir')."/public";
    }

    public function format(string $path)
    {
        if (str_contains($path, "@")) return "mailto: ".str_lstrip(trim($path), "mailto:");
        if (filter_var($path, FILTER_VALIDATE_URL))
            return $path;

        if(str_starts_with($path, "/")) 
            return $this->getPublicDir().$path;

        return realpath($this->getPublicDir()."/".str_lstrip($this->localeUri, "/")."/".$path);
    }

    public function getPublicDir(): string { return $this->publicDir; }

    public function isSafePlace($fname)
    {
        if (filter_var($fname, FILTER_VALIDATE_URL)) return true;
        if (str_starts_with($fname, $this->getPublicDir())) {

            $base = explode("/", str_lstrip($fname, $this->getPublicDir()), 1)[0];
            if(!in_array($base, ["bundles", "assets", "storage"])) return true;
        }

        return false;
    }

    public function datetime(DateTime|string|null $value, string $format = DateTimeInterface::RFC3339): ?string
    {
        if($value === null) return null;

        $now = new DateTime("now");
        $datetime = $value instanceof DateTime ? $value : DateTime::createFromFormat($format, $value);
        if(!$datetime || $datetime->format($format) !== $value)
            $datetime = $now->modify($value);

        if($datetime && $datetime->format($format) === $value && $datetime > $now)
            return $datetime->format($format);

        return null;
    }

    protected function security(): ?string
    {
        if(!$this->enable) return null;

        $fname = $this->format("security.txt");
        if($this->filesystem->exists($fname) && !$this->overrideExistingFiles)
            return null;

        if(!$this->isSafePlace($fname)) 
            return null;

        $security = "";
        $canonical  = $this->parameterBag->get("well_known.security_txt.canonical") ?? null;
        if($canonical) $security .= "Canonical: ".$this->format($canonical).'\n\n';

        $encryption = $this->parameterBag->get("well_known.security_txt.encryption") ?? null;
        if($encryption) $security .= "Encryption: ".$this->format($encryption).'\n\n';

        $expires    = $this->datetime($this->parameterBag->get("well_known.security_txt.expires"));
        if($expires) $security .= "Expires: ".$expires.'\n\n';

        $contacts   = $this->parameterBag->get("well_known.security_txt.contacts") ?? [];
        foreach($contacts ?? [] as $contact)
            $security .= "Contact: ".$this->format($contact).'\n';
        if(count($contacts)) $security .= "\n";
        
        $format    = $this->parameterBag->get("well_known.security_txt.acknowledgements");
        if($format) $security .= "Acknowledgements: ".$this->format($format).'\n\n';

        $policy    = $this->parameterBag->get("well_known.security_txt.policy");
        if($policy) $security .= "Policy: ".$this->format($policy).'\n\n';
        
        $hiring    = $this->parameterBag->get("well_known.security_txt.hirting");
        if($hiring) $security .= "Hiring: ".$this->format($hiring).'\n\n';

        $preferredLanguages = $this->parameterBag->get("well_known.security_txt.preferred_languages");
        if($preferredLanguages) $security .= "Preferred-Languages: ".implode(",", $preferredLanguages);

        $this->filesystem->dumpFile($fname, $security);
        return $fname;
    }

    protected function robots(): ?string
    {
        if(!$this->enable) return null;

        $fname = $this->format("robots.txt");
        if($this->filesystem->exists($fname) && !$this->overrideExistingFiles)
            return null;

        if(!$this->isSafePlace($fname)) 
            return null;

        $robots = "";
        $entries = $this->parameterBag->get("well_known.robots_txt") ?? [];
        foreach($entries as $entry) {

            foreach($entry["user-agent"] ?? [] as $_)
                $robots .= "User-Agent: ".$_;
            foreach($entry["disallow"] ?? [] as $_)
                $robots .= "Disallow: ".$this->format($_);
        }

        $this->filesystem->dumpFile($fname, $robots);
        return $fname;
    }

    protected function humans(): ?string
    {
        if(!$this->enable) return null;

        $fname = $this->format("humans.txt");
        if($this->filesystem->exists($fname) && !$this->overrideExistingFiles)
            return null;

        if(!$this->isSafePlace($fname)) 
            return null;

        $humansTxt = $this->parameterBag->get("well_known.humans_txt") ?? null;
        $this->filesystem->dumpFile($fname, $humansTxt ? file_get_contents($humansTxt) : null);
        
        return null;
    }

    protected function ads(): ?string
    {
        if(!$this->enable) return null;
        
        $fname = $this->format("ads.txt");
        if($this->filesystem->exists($fname) && !$this->overrideExistingFiles)
            return null;

        if(!$this->isSafePlace($fname)) 
            return null;

        $ads = "";
        $entries = $this->parameterBag->get("well_known.ads_txt") ?? [];
        foreach($entries as $entry)
            $ads .= implode(" ", $entry);

        $this->filesystem->dumpFile($fname, $ads);
        
        return null;
    }
}