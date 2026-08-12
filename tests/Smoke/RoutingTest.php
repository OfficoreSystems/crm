<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Haelt fest, wie Modulrouten geladen werden.
 *
 * Die Routen kommen aus {@see \App\Kernel::configureRoutes()} ueber die Liste
 * der registrierten Bundles, nicht ueber einen Glob auf modules/. Der
 * Unterschied ist nicht kosmetisch: ein Glob liest das Dateisystem, waehrend
 * die Registrierung ueber config/bundles.php laeuft. Ein deregistriertes Modul
 * behaelt dabei seine Routen, und der erste Aufruf endet mit 500 statt 404.
 */
final class RoutingTest extends KernelTestCase
{
    /**
     * Der Regressionstest fuer den teuersten Fehler dieses Umbaus.
     *
     * Die Bundle-Schleife lief zuerst ueber *alle* Bundles. LiveComponentBundle
     * bringt selbst eine config/routes.php mit, die normalerweise mit dem
     * Praefix /_components importiert wird. Ohne Praefix wird daraus
     * /{_live_component}/{_live_action} - eine Route, die jeden einteiligen
     * Pfad schluckt. Das Symptom war ein 404 auf /contacts, obwohl
     * debug:router die richtige Route sauber anzeigte: gematcht hat eine
     * andere.
     */
    #[Test]
    #[DataProvider('expectedRoutes')]
    public function the_expected_route_matches(string $path, string $routeName): void
    {
        self::assertSame($routeName, $this->match($path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function expectedRoutes(): iterable
    {
        yield 'Startseite aus dem Core' => ['/', 'home'];
        yield 'Modul contact' => ['/contacts', 'contact_index'];
        yield 'Modul user, Anmeldung' => ['/login', 'user_login'];
        yield 'Modul user, Verwaltung' => ['/users', 'user_index'];
    }

    #[Test]
    public function the_live_component_route_keeps_its_prefix(): void
    {
        // Sie darf nur unter /_components erreichbar sein. Liegt sie an der
        // Wurzel, verdeckt sie jede einteilige Modulroute.
        self::assertSame('ux_live_component', $this->match('/_components/ContactList'));
    }

    #[Test]
    public function no_route_outside_the_components_prefix_is_a_live_component_route(): void
    {
        self::bootKernel();
        $router = static::getContainer()->get(RouterInterface::class);

        foreach ($router->getRouteCollection() as $name => $route) {
            if (!\array_key_exists('_live_component', $route->getDefaults() + array_flip($route->compile()->getVariables()))) {
                continue;
            }

            self::assertStringStartsWith(
                '/_components',
                $route->getPath(),
                sprintf('Route "%s" nimmt Live-Component-Parameter, liegt aber nicht unter /_components.', $name),
            );
        }
    }

    #[Test]
    public function only_crm_modules_contribute_their_own_routes(): void
    {
        self::bootKernel();
        $bundles = static::getContainer()->getParameter('kernel.bundles');
        self::assertIsArray($bundles);

        // Beide Module sind registriert und liefern jeweils eine Route.
        self::assertArrayHasKey('ContactModule', $bundles);
        self::assertArrayHasKey('UserModule', $bundles);

        $names = array_keys(iterator_to_array(
            static::getContainer()->get(RouterInterface::class)->getRouteCollection(),
        ));

        self::assertContains('contact_index', $names);
        self::assertContains('user_login', $names);
    }

    private function match(string $path): string
    {
        self::bootKernel();

        /** @var array{_route: string} $match */
        $match = static::getContainer()->get(RouterInterface::class)->match($path);

        return $match['_route'];
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();

        parent::tearDown();
    }
}
