<?php

declare(strict_types=1);

namespace Crm\Document\UI\Twig;

use Crm\Document\Domain\FileSize;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Dateigroessen lesbar machen.
 *
 * Eine Zahl wie 26214400 sagt niemandem etwas; "25.0 MB" schon. Die Umrechnung
 * liegt bewusst nicht im Template: sie wird auch fuer die Kennzahl auf der
 * Uebersicht und fuer die Fehlermeldung beim Upload gebraucht, und drei
 * Kopien wuerden auseinanderlaufen.
 */
final class DocumentExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('document_size', FileSize::humanize(...)),
        ];
    }
}
