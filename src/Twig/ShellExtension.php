<?php

declare(strict_types=1);

namespace App\Twig;

use Crm\SharedKernel\Localization\Locale;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Was das Grundlayout wissen muss, ohne ein Modul zu kennen.
 *
 * Beide Funktionen hier haben denselben Hintergrund: die Seitenleiste steht im
 * Core und soll trotzdem Dinge anbieten, die aus Modulen kommen.
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
     * Gibt es diese Route?
     *
     * Klingt nach einem Umweg und ist einer - aber der guenstigste. Das
     * Layout will einen Sprachumschalter anbieten, der ins user-Modul fuehrt.
     * Ohne dieses Modul gibt es die Route nicht, und path() wuerde die ganze
     * Seite mit einer Ausnahme abbrechen.
     *
     * Die Alternative waere ein Extension-Point im Shared Kernel, ueber den
     * Module "Kontoaktionen" beisteuern. Fuer genau einen Eintrag waere das
     * mehr Gerüst als Nutzen; kommt ein zweiter dazu, ist es der richtige
     * Zeitpunkt dafuer.
     */
    public function routeExists(string $name): bool
    {
        return null !== $this->router->getRouteCollection()->get($name);
    }
}
