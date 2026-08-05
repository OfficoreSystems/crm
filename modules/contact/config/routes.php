<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Das Modul bringt seine Routen selbst mit. Der Core importiert nur den Glob
 * `modules/*` + `/config/routes.php` und kennt damit die Konvention, aber
 * kein einzelnes Modul.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import(__DIR__.'/../src/UI/Controller/', 'attribute');
};
