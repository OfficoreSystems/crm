<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Subject;

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
     * @param array<string, string|int> $routeParameters
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $label,
        public ?string $route = null,
        public array $routeParameters = [],
        public ?string $typeLabel = null,
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
