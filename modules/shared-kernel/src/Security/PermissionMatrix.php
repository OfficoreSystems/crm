<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Who may do what, and how far.
 *
 * Laid out as role → module → action → scope, with `*` as a wildcard on both
 * middle levels. The wildcard matters: without it the matrix would have to know
 * every module, and a new module would be a change here - exactly what
 * modularity is meant to avoid. An unknown module thus falls back to the
 * default of its role.
 *
 * If an entry is missing everywhere, the answer is: forbidden. Rights are
 * granted, not revoked.
 */
final readonly class PermissionMatrix
{
    public const ANY = '*';

    /**
     * @param array<string, array<string, array<string, AccessScope>>> $matrix role => module => action => scope
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
                // Create it even when no action follows: an empty entry is how
                // one says "nothing at all for this module". Without this line
                // it would vanish silently and the wildcard would apply after
                // all.
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
     * The default the system starts with.
     *
     * Deliberately generous about reading and narrow about changing: in a CRM
     * shared knowledge is the whole point, but nobody should rewrite someone
     * else's deals. Master data such as companies and contacts belongs to
     * everyone; deals and activities belong to their owner.
     */
    public static function default(): self
    {
        return self::fromArray([
            // The administrator may do everything, everywhere.
            'ROLE_ADMIN' => [
                self::ANY => [
                    Action::VIEW->value => AccessScope::ALL,
                    Action::CREATE->value => AccessScope::ALL,
                    Action::EDIT->value => AccessScope::ALL,
                    Action::DELETE->value => AccessScope::ALL,
                ],
            ],
            'ROLE_USER' => [
                // Default for every module, including ones added later: read
                // within the team, create, change your own data, delete
                // nothing.
                self::ANY => [
                    Action::VIEW->value => AccessScope::TEAM,
                    Action::CREATE->value => AccessScope::ALL,
                    Action::EDIT->value => AccessScope::OWN,
                ],
                // Master data is shared knowledge. Named modules are listed
                // exhaustively - whatever is missing here is forbidden.
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
                // Overview and search only show what is visible anyway - the
                // restriction happens one level down.
                'dashboard' => [Action::VIEW->value => AccessScope::ALL],
                'search' => [Action::VIEW->value => AccessScope::ALL],
                // User administration stays reserved for the administrator.
                // The empty entry is the statement: the default does *not*
                // apply here.
                'user' => [],
            ],
        ]);
    }

    /**
     * The widest scope these roles yield for the module and action.
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
     * Everything a role may do - for an overview in the interface.
     *
     * @return array<string, array<string, AccessScope>> module => action => scope
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

        // A named entry is complete: it does *not* fall back to the wildcard.
        // Otherwise "nothing at all for this module" could not be expressed -
        // every attempt would land back at the default. The wildcard is thus
        // the rule for modules that do not appear here, and in particular for
        // ones added later.
        if (\array_key_exists($module, $rules)) {
            return $rules[$module][$action->value] ?? $rules[$module][self::ANY] ?? null;
        }

        return $rules[self::ANY][$action->value] ?? $rules[self::ANY][self::ANY] ?? null;
    }
}
