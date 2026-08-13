<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

use Crm\SharedKernel\Localization\Locale;

/**
 * Whoever is currently acting.
 *
 * The voter in the shared kernel needs to know the user ID and the team, but
 * must not know the user module. Hence this narrow contract: the user module
 * lets its SecurityUser implement it, and the voter only checks against that.
 *
 * Without the user module there is no signed-in user - and then the voter does
 * not apply anyway.
 */
interface ActorInterface
{
    public function actorId(): string;

    /**
     * The team, or null when assigned to none.
     *
     * Null carries meaning: whoever is in no team sees only their own data under
     * TEAM rights. Anything else would be a hole - a user without a team would
     * otherwise see the data of every other user without a team.
     */
    public function actorTeamId(): ?string;

    /**
     * @return list<string>
     */
    public function actorRoles(): array;

    /**
     * The language this user wants to see the application in.
     *
     * It lives here and not in a contract of its own: "who is currently acting"
     * is exactly the question whose answer also determines the language. A
     * second interface would have the same implementation and a second name.
     *
     * Null means "never chosen" - then {@see Locale::default()} applies.
     */
    public function actorLocale(): ?Locale;
}
