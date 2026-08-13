<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Subject;

use Crm\SharedKernel\Localization\TranslatableText;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Ein aufgeloester Verweis: genug, um ihn anzuzeigen und zu verlinken.
 *
 * Bewusst Routenname plus Parameter statt einer fertigen URL. Die URL zu
 * bauen ist Aufgabe des Templates, das den Router kennt - und ein
 * vorgefertigter Pfad waere beim Wechsel des Routings still falsch.
 */
final readonly class ResolvedSubject
{
    /**
     * @param string                             $label           Daten, kein Schluessel - der Name
     *                                                            des Datensatzes.
     * @param array<string, string|int>          $routeParameters
     * @param string|TranslatableInterface|null  $typeLabel       Uebersetzungsschluessel.
     * @param string|TranslatableInterface|null  $description     Zweite Zeile: was diesen Datensatz
     *                                                            von gleichnamigen unterscheidet.
     *                                                            Ohne sie ist eine Trefferliste mit
     *                                                            drei "Nordwind" nicht benutzbar.
     *                                                            Mischt oft Daten und Uebersetzung -
     *                                                            dafuer gibt es
     *                                                            {@see TranslatableText}.
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $label,
        public ?string $route = null,
        public array $routeParameters = [],
        public string|TranslatableInterface|null $typeLabel = null,
        public string|TranslatableInterface|null $description = null,
    ) {
    }

    public function isLinkable(): bool
    {
        return null !== $this->route;
    }

    public function key(): string
    {
        return SubjectRef::keyFor($this->type, $this->id);
    }
}
