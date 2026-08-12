<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Wer darf was, und wie weit.
 *
 * Aufgebaut als Rolle → Modul → Aktion → Scope, mit `*` als Platzhalter auf
 * beiden mittleren Ebenen. Der Platzhalter ist wichtig: ohne ihn muesste die
 * Matrix jedes Modul kennen, und ein neues Modul waere eine Aenderung hier -
 * genau das, was die Modularitaet vermeiden soll. Ein unbekanntes Modul faellt
 * so auf die Vorgabe seiner Rolle zurueck.
 *
 * Fehlt ein Eintrag ueberall, gilt: verboten. Rechte werden vergeben, nicht
 * entzogen.
 */
final readonly class PermissionMatrix
{
    public const ANY = '*';

    /**
     * @param array<string, array<string, array<string, AccessScope>>> $matrix Rolle => Modul => Aktion => Scope
     */
    private function __construct(
        private array $matrix,
    ) {
    }

    /**
     * @param array<string, array<string, array<string, AccessScope|string>>> $matrix
     */
    public static function fromArray(array $matrix): self
    {
        $normalized = [];

        foreach ($matrix as $role => $modules) {
            $normalized[$role] ??= [];

            foreach ($modules as $module => $actions) {
                // Auch dann anlegen, wenn keine Aktion folgt: ein leerer
                // Eintrag ist die Art, "fuer dieses Modul gar nichts" zu
                // sagen. Ohne diese Zeile verschwaende er sich lautlos und
                // der Platzhalter griffe doch.
                $normalized[$role][$module] ??= [];

                foreach ($actions as $action => $scope) {
                    $normalized[$role][$module][$action] = $scope instanceof AccessScope
                        ? $scope
                        : AccessScope::from($scope);
                }
            }
        }

        return new self($normalized);
    }

    /**
     * Die Vorgabe, mit der das System startet.
     *
     * Bewusst grosszuegig beim Lesen und eng beim Aendern: in einem CRM ist
     * gemeinsames Wissen der Sinn der Sache, aber niemand soll fremde
     * Verkaufschancen umschreiben. Stammdaten wie Firmen und Kontakte gehoeren
     * allen, Verkaufschancen und Aktivitaeten ihrem Besitzer.
     */
    public static function default(): self
    {
        return self::fromArray([
            // Der Administrator darf alles, ueberall.
            'ROLE_ADMIN' => [
                self::ANY => [
                    Action::VIEW->value => AccessScope::ALL,
                    Action::CREATE->value => AccessScope::ALL,
                    Action::EDIT->value => AccessScope::ALL,
                    Action::DELETE->value => AccessScope::ALL,
                ],
            ],
            'ROLE_USER' => [
                // Vorgabe fuer jedes Modul, auch fuer spaeter hinzukommende:
                // im Team lesen und anlegen, eigene Daten aendern, nichts
                // loeschen.
                self::ANY => [
                    Action::VIEW->value => AccessScope::TEAM,
                    Action::CREATE->value => AccessScope::ALL,
                    Action::EDIT->value => AccessScope::OWN,
                ],
                // Stammdaten sind gemeinsames Wissen. Genannte Module sind
                // vollstaendig aufgezaehlt - was hier fehlt, ist verboten.
                'company' => [
                    Action::VIEW->value => AccessScope::ALL,
                    Action::CREATE->value => AccessScope::ALL,
                    Action::EDIT->value => AccessScope::ALL,
                ],
                'contact' => [
                    Action::VIEW->value => AccessScope::ALL,
                    Action::CREATE->value => AccessScope::ALL,
                    Action::EDIT->value => AccessScope::ALL,
                ],
                // Uebersicht und Suche zeigen nur, was ohnehin sichtbar ist -
                // die Einschraenkung passiert eine Ebene tiefer.
                'dashboard' => [Action::VIEW->value => AccessScope::ALL],
                'search' => [Action::VIEW->value => AccessScope::ALL],
                // Die Benutzerverwaltung bleibt dem Administrator
                // vorbehalten. Der leere Eintrag ist die Aussage: hier gilt
                // die Vorgabe *nicht*.
                'user' => [],
            ],
        ]);
    }

    /**
     * Der weiteste Scope, den diese Rollen fuer Modul und Aktion hergeben.
     *
     * @param list<string> $roles
     */
    public function scopeFor(array $roles, string $module, Action $action): ?AccessScope
    {
        $found = [];

        foreach ($roles as $role) {
            $scope = $this->scopeForRole($role, $module, $action);

            if (null !== $scope) {
                $found[] = $scope;
            }
        }

        return AccessScope::widest($found);
    }

    /**
     * @param list<string> $roles
     */
    public function allows(array $roles, string $module, Action $action): bool
    {
        return null !== $this->scopeFor($roles, $module, $action);
    }

    /**
     * Alles, was eine Rolle darf - fuer eine Uebersicht in der Oberflaeche.
     *
     * @return array<string, array<string, AccessScope>> Modul => Aktion => Scope
     */
    public function forRole(string $role): array
    {
        return $this->matrix[$role] ?? [];
    }

    private function scopeForRole(string $role, string $module, Action $action): ?AccessScope
    {
        $rules = $this->matrix[$role] ?? null;

        if (null === $rules) {
            return null;
        }

        // Ein genannter Eintrag ist vollstaendig: er faellt *nicht* auf den
        // Platzhalter zurueck. Sonst liesse sich "fuer dieses Modul gar
        // nichts" nicht ausdruecken - jeder Versuch landete wieder bei der
        // Vorgabe. Der Platzhalter ist damit die Regel fuer Module, die hier
        // nicht vorkommen, also insbesondere fuer spaeter hinzukommende.
        if (\array_key_exists($module, $rules)) {
            return $rules[$module][$action->value] ?? $rules[$module][self::ANY] ?? null;
        }

        return $rules[self::ANY][$action->value] ?? $rules[self::ANY][self::ANY] ?? null;
    }
}
