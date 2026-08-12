<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Wie weit ein Recht reicht.
 *
 * Nicht "darf/darf nicht", sondern "darf wofuer": ein Vertriebler darf Deals
 * bearbeiten - aber nur seine eigenen. Ohne diese Abstufung braeuchte man fuer
 * jede Kombination eine eigene Rolle.
 *
 * Kein Fall fuer "gar nicht": das ist die Abwesenheit eines Eintrags in der
 * Matrix. Ein expliziter NONE-Fall waere eine zweite Art, dasselbe zu sagen -
 * und irgendwann stuenden beide nebeneinander.
 */
enum AccessScope: string
{
    /**
     * Nur eigene Datensaetze.
     */
    case OWN = 'own';

    /**
     * Alles aus dem eigenen Team.
     */
    case TEAM = 'team';

    /**
     * Alles.
     */
    case ALL = 'all';

    public function label(): string
    {
        return match ($this) {
            self::OWN => 'nur eigene',
            self::TEAM => 'eigenes Team',
            self::ALL => 'alle',
        };
    }

    /**
     * Je hoeher, desto weiter reichend.
     *
     * Gebraucht, wenn ein Benutzer mehrere Rollen hat: dann gewinnt die
     * weiteste. Andernfalls koennte eine zusaetzliche Rolle Rechte *wegnehmen*,
     * was niemand erwartet.
     */
    public function rank(): int
    {
        return match ($this) {
            self::OWN => 1,
            self::TEAM => 2,
            self::ALL => 3,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * Die weiteste aus einer Menge, oder null wenn die Menge leer ist.
     *
     * @param list<self> $scopes
     */
    public static function widest(array $scopes): ?self
    {
        $widest = null;

        foreach ($scopes as $scope) {
            if (null === $widest || $scope->rank() > $widest->rank()) {
                $widest = $scope;
            }
        }

        return $widest;
    }
}
