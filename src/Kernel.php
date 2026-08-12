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
     * Laedt die Routen des Cores und anschliessend die der *registrierten*
     * Module.
     *
     * Vorher stand dafuer ein Glob in config/routes.yaml ueber
     * modules/[*]/config/routes.php. Der liest das Dateisystem, waehrend die
     * Registrierung ueber config/bundles.php laeuft - und beides kann
     * auseinanderlaufen. Nimmt man ein Modul aus bundles.php, verschwinden
     * seine Services, seine Routen bleiben, und der erste Aufruf endet mit
     * "has no container set, did you forget to define it as a service
     * subscriber?" - einem 500 statt eines schlichten 404.
     *
     * Ueber die Bundle-Liste gibt es genau eine Wahrheit: was nicht
     * registriert ist, hat auch keine Routen.
     *
     * Zwei Fallstricke, die hier je einmal zugeschlagen haben:
     *
     * 1. Die Vorlage aus MicroKernelTrait muss vollstaendig mitkopiert werden,
     *    weil eine eigene configureRoutes() sie komplett ersetzt. Die Klammern
     *    um "config" sind kein Tippfehler, sondern machen den Pfad fuer den
     *    Glob-Loader auswertbar.
     *
     * 2. Die Schleife darf *nicht* ueber alle Bundles laufen. Fremdbundles
     *    bringen ebenfalls config/routes.php mit - LiveComponentBundle etwa.
     *    Die wird von config/routes/ux_live_component.yaml bereits mit dem
     *    Praefix /_components importiert; ein zweiter Import ohne Praefix
     *    erzeugt eine Route /{_live_component}/{_live_action}, die jeden Pfad
     *    gierig schluckt. Das Symptom war ein 404 auf /contacts, obwohl
     *    debug:router die Route sauber anzeigte - gematcht hat schlicht eine
     *    andere.
     *
     * Der Filter auf CrmModuleInterface loest das: Der Core kennt damit den
     * Vertrag aus dem Shared Kernel, aber kein einzelnes Modul.
     */
    /*
     * Bewusst protected, nicht private.
     *
     * MicroKernelTrait ruft die Methode ueber Reflection auf
     * (ReflectionMethod::getClosure), also ohne statisch sichtbaren Aufruf.
     * PHPStan meldet sie als private Methode deshalb zu Recht als unbenutzt.
     * Protected ist keine Unterdrueckung der Regel, sondern die ehrlichere
     * Angabe: der Kernel ist zum Erweitern gedacht.
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
