<?php

declare(strict_types=1);

namespace App;

use Crm\SharedKernel\Module\CrmModuleInterface;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Loads the core's routes and then those of the *registered* modules.
     *
     * This used to be a glob in config/routes.yaml over
     * modules/[*]/config/routes.php. That reads the file system while
     * registration runs through config/bundles.php - and the two can drift
     * apart. Take a module out of bundles.php and its services disappear while
     * its routes stay, so the first call ends in "has no container set, did you
     * forget to define it as a service subscriber?" - a 500 instead of a plain
     * 404.
     *
     * Going through the bundle list leaves exactly one truth: what is not
     * registered has no routes either.
     *
     * Two traps, each of which caught us once:
     *
     * 1. The template from MicroKernelTrait has to be copied in full, because
     *    an own configureRoutes() replaces it entirely. The braces around
     *    "config" are not a typo - they make the path evaluable for the glob
     *    loader.
     *
     * 2. The loop must *not* run over every bundle. Third-party bundles ship
     *    config/routes.php too - LiveComponentBundle, for one. That file is
     *    already imported by config/routes/ux_live_component.yaml with the
     *    prefix /_components; a second import without the prefix produces a
     *    route /{_live_component}/{_live_action} that greedily swallows every
     *    path. The symptom was a 404 on /contacts even though debug:router
     *    displayed the route just fine - a different one was matching.
     *
     * The filter on CrmModuleInterface solves it: the core thereby knows the
     * contract from the shared kernel, but no individual module.
     */
    /*
     * Deliberately protected, not private.
     *
     * MicroKernelTrait calls the method through reflection
     * (ReflectionMethod::getClosure), so there is no statically visible call.
     * PHPStan is therefore right to report a private method as unused.
     * Protected is not a way of suppressing the rule but the more honest
     * statement: the kernel is meant to be extended.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $configDir = preg_replace('{/config$}', '/{config}', $this->getConfigDir());

        $routes->import($configDir.'/{routes}/'.$this->environment.'/*.{php,yaml}');
        $routes->import($configDir.'/{routes}/*.{php,yaml}');

        if (is_file($this->getConfigDir().'/routes.yaml')) {
            $routes->import($configDir.'/routes.yaml');
        } else {
            $routes->import($configDir.'/{routes}.php');
        }

        if ($fileName = (new \ReflectionObject($this))->getFileName()) {
            $routes->import($fileName, 'attribute');
        }

        foreach ($this->getBundles() as $bundle) {
            if (!$bundle instanceof CrmModuleInterface) {
                continue;
            }

            $routeFile = $bundle->getPath().'/config/routes.php';

            if (is_file($routeFile)) {
                $routes->import($routeFile);
            }
        }
    }
}
