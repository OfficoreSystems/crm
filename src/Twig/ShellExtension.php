<?php

declare(strict_types=1);

namespace App\Twig;

use Crm\SharedKernel\Localization\Locale;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * What the shell layout needs to know without knowing a module.
 *
 * Both functions here share the same background: the sidebar lives in the core
 * and still wants to offer things that come from modules.
 */
final class ShellExtension extends AbstractExtension
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('crm_locales', $this->locales(...)),
            new TwigFunction('crm_route_exists', $this->routeExists(...)),
        ];
    }

    /**
     * @return list<Locale>
     */
    public function locales(): array
    {
        return Locale::all();
    }

    /**
     * Does this route exist?
     *
     * Sounds like a detour and is one - but the cheapest available. The layout
     * wants to offer a language switcher that leads into the user module.
     * Without that module the route does not exist, and path() would abort the
     * whole page with an exception.
     *
     * The alternative would be an extension point in the shared kernel through
     * which modules contribute "account actions". For exactly one entry that
     * would be more scaffolding than benefit; once a second one shows up, that
     * is the right moment for it.
     */
    public function routeExists(string $name): bool
    {
        return null !== $this->router->getRouteCollection()->get($name);
    }
}
