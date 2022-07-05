<?php

namespace Well\Known\Subscriber;

use \Symfony\Component\HttpKernel\Event\RequestEvent;
use \Symfony\Component\HttpFoundation\Response;

use Twig\Environment;
use Base\Service\BaseService;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class WellKnownListener
{
    private $twig;

    public function __construct(ParameterBagInterface $parameterBag, Environment $twig, RequestStack $requestStack)
    {
        $this->twig         = $twig;
        $this->requestStack = $requestStack;

        $this->enable = $parameterBag->get("well_known.enable");
        $this->autoAppend = $parameterBag->get("well_known.autoappend");
        $this->websiteId = $parameterBag->get("well_known.website_id");
    }

    private function allowRender(ResponseEvent $event): bool
    {
        if (!$this->websiteId) return false;
        if (!$this->autoAppend)
            return false;
        
            $contentType = $event->getResponse()->headers->get('content-type');
        if ($contentType && !str_contains($contentType, "text/html"))
            return false;
    
        if (!$event->isMainRequest())
            return false;

        if ($this->isEasyAdmin($event))
            return false;
    
        return !$this->isProfiler($event);
    }

    public function isProfiler($event)
    {
        $route = $event->getRequest()->get('_route');
        return str_starts_with($route, "_wdt") || str_starts_with($route, "_profiler");
    }

    public function isEasyAdmin($event)
    {
        $controllerAttribute = $event->getRequest()->attributes->get("_controller");
        $array = is_array($controllerAttribute) ? $controllerAttribute : explode("::", $event->getRequest()->attributes->get("_controller"));
        $controller = explode("::", $array[0])[0];

        $parents = [];
        $parent = $controller;
        while(class_exists($parent) && ( $parent = get_parent_class($parent)))
            $parents[] = $parent;

        $eaParents = array_filter($parents, fn($c) => str_starts_with($c, "EasyCorp\Bundle\EasyAdminBundle"));
        return !empty($eaParents);
    }

    public function getAsset(string $url): string
    {
        $url = trim($url);
        $parseUrl = parse_url($url);
        if($parseUrl["scheme"] ?? false)
            return $url;

        $request = $this->requestStack->getCurrentRequest();
        $baseDir = $request ? $request->getBasePath() : $_SERVER["CONTEXT_PREFIX"] ?? "";

        $path = trim($parseUrl["path"]);
        if($path == "/") return $baseDir;
        else if(!str_starts_with($path, "/"))
            $path = $baseDir."/".$path;

        return $path;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$this->enable) return;
        if (!$this->websiteId) return;

        if (!$event->isMainRequest()) return false;

        if ($this->isProfiler ($event)) return false;
        if ($this->isEasyAdmin($event)) return false;

        $locale = substr($event->getRequest()->getLocale(),0,2);
        $javascript = '<script id="well_known-script" data-locale="'.$locale.'" data-website-id="'.$this->websiteId.'" src="'.$this->getAsset("bundles/well_known/well_known.js").'"></script>';

        $this->twig->addGlobal("well_known", $this->twig->getGlobals()["well_known"] ?? "" . $javascript);
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$this->allowRender($event)) return false;

        $response = $event->getResponse();
        $javascript = $this->twig->getGlobals()["well_known"] ?? "";

        $content = preg_replace(['/<\/body\b[^>]*>/'], [$javascript."$0"], $response->getContent(), 1);
        $response->setContent($content);

        return true;
    }
}