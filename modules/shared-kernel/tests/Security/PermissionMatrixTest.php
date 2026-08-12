<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Security;

use Crm\SharedKernel\Security\AccessScope;
use Crm\SharedKernel\Security\Action;
use Crm\SharedKernel\Security\PermissionMatrix;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PermissionMatrixTest extends TestCase
{
    #[Test]
    public function a_missing_entry_means_denied(): void
    {
        // Rechte werden vergeben, nicht entzogen. Alles, was nicht in der
        // Matrix steht, ist verboten.
        $matrix = PermissionMatrix::fromArray([]);

        self::assertNull($matrix->scopeFor(['ROLE_USER'], 'deal', Action::VIEW));
        self::assertFalse($matrix->allows(['ROLE_USER'], 'deal', Action::VIEW));
    }

    #[Test]
    public function an_unknown_role_gets_nothing(): void
    {
        self::assertFalse(PermissionMatrix::default()->allows(['ROLE_GAST'], 'deal', Action::VIEW));
    }

    #[Test]
    public function a_named_module_replaces_the_wildcard_entirely(): void
    {
        // Ein genannter Eintrag faellt *nicht* auf den Platzhalter zurueck.
        // Sonst liesse sich "fuer dieses Modul gar nichts" nicht ausdruecken.
        $matrix = PermissionMatrix::fromArray([
            'ROLE_USER' => [
                PermissionMatrix::ANY => [
                    Action::VIEW->value => AccessScope::OWN,
                    Action::EDIT->value => AccessScope::OWN,
                ],
                'company' => [Action::VIEW->value => AccessScope::ALL],
            ],
        ]);

        self::assertSame(AccessScope::OWN, $matrix->scopeFor(['ROLE_USER'], 'deal', Action::VIEW));
        self::assertSame(AccessScope::ALL, $matrix->scopeFor(['ROLE_USER'], 'company', Action::VIEW));
        self::assertNull(
            $matrix->scopeFor(['ROLE_USER'], 'company', Action::EDIT),
            'company ist vollstaendig aufgezaehlt - edit fehlt dort, also verboten.',
        );
    }

    #[Test]
    public function an_empty_module_entry_denies_everything(): void
    {
        // Die einzige Art, ein Modul von der Vorgabe auszunehmen.
        $matrix = PermissionMatrix::fromArray([
            'ROLE_USER' => [
                PermissionMatrix::ANY => [Action::VIEW->value => AccessScope::ALL],
                'user' => [],
            ],
        ]);

        self::assertSame(AccessScope::ALL, $matrix->scopeFor(['ROLE_USER'], 'deal', Action::VIEW));
        self::assertNull($matrix->scopeFor(['ROLE_USER'], 'user', Action::VIEW));
    }

    #[Test]
    public function an_unknown_module_falls_back_to_the_wildcard(): void
    {
        // Der Grund fuer den Platzhalter: ein neues Modul darf keine Aenderung
        // an der Matrix erzwingen.
        $matrix = PermissionMatrix::default();

        self::assertSame(
            AccessScope::TEAM,
            $matrix->scopeFor(['ROLE_USER'], 'gibt-es-noch-nicht', Action::VIEW),
        );
    }

    #[Test]
    public function the_widest_scope_of_all_roles_wins(): void
    {
        // Sonst koennte eine zusaetzliche Rolle Rechte *wegnehmen* - das
        // erwartet niemand.
        $matrix = PermissionMatrix::fromArray([
            'ROLE_USER' => [PermissionMatrix::ANY => [Action::VIEW->value => AccessScope::OWN]],
            'ROLE_LEAD' => [PermissionMatrix::ANY => [Action::VIEW->value => AccessScope::TEAM]],
        ]);

        self::assertSame(
            AccessScope::TEAM,
            $matrix->scopeFor(['ROLE_USER', 'ROLE_LEAD'], 'deal', Action::VIEW),
        );
    }

    #[Test]
    public function the_default_lets_an_admin_do_everything(): void
    {
        $matrix = PermissionMatrix::default();

        foreach (Action::cases() as $action) {
            foreach (['deal', 'contact', 'user', 'irgendwas-neues'] as $module) {
                self::assertSame(
                    AccessScope::ALL,
                    $matrix->scopeFor(['ROLE_ADMIN'], $module, $action),
                    sprintf('ROLE_ADMIN sollte %s auf %s duerfen', $action->value, $module),
                );
            }
        }
    }

    #[Test]
    public function the_default_keeps_deals_inside_the_team(): void
    {
        $matrix = PermissionMatrix::default();

        self::assertSame(AccessScope::TEAM, $matrix->scopeFor(['ROLE_USER'], 'deal', Action::VIEW));
        self::assertSame(AccessScope::OWN, $matrix->scopeFor(['ROLE_USER'], 'deal', Action::EDIT));
    }

    #[Test]
    public function the_default_treats_master_data_as_shared_knowledge(): void
    {
        // In einem CRM ist gemeinsames Wissen der Sinn der Sache.
        $matrix = PermissionMatrix::default();

        foreach (['company', 'contact'] as $module) {
            self::assertSame(AccessScope::ALL, $matrix->scopeFor(['ROLE_USER'], $module, Action::VIEW));
            self::assertSame(AccessScope::ALL, $matrix->scopeFor(['ROLE_USER'], $module, Action::EDIT));
        }
    }

    #[Test]
    public function the_default_lets_nobody_but_the_admin_delete(): void
    {
        $matrix = PermissionMatrix::default();

        foreach (['deal', 'contact', 'company', 'activity'] as $module) {
            self::assertFalse(
                $matrix->allows(['ROLE_USER'], $module, Action::DELETE),
                sprintf('ROLE_USER sollte %s nicht loeschen duerfen', $module),
            );
        }
    }

    #[Test]
    public function the_default_reserves_user_administration_for_admins(): void
    {
        $matrix = PermissionMatrix::default();

        self::assertFalse($matrix->allows(['ROLE_USER'], 'user', Action::VIEW));
        self::assertTrue($matrix->allows(['ROLE_ADMIN'], 'user', Action::VIEW));
    }

    #[Test]
    public function it_accepts_plain_strings_as_scopes(): void
    {
        $matrix = PermissionMatrix::fromArray([
            'ROLE_USER' => [PermissionMatrix::ANY => [Action::VIEW->value => 'team']],
        ]);

        self::assertSame(AccessScope::TEAM, $matrix->scopeFor(['ROLE_USER'], 'deal', Action::VIEW));
    }

    #[Test]
    public function it_can_report_everything_a_role_may_do(): void
    {
        self::assertNotSame([], PermissionMatrix::default()->forRole('ROLE_ADMIN'));
        self::assertSame([], PermissionMatrix::default()->forRole('ROLE_GAST'));
    }

    #[Test]
    public function scopes_are_ordered_from_narrow_to_wide(): void
    {
        self::assertTrue(AccessScope::ALL->isAtLeast(AccessScope::TEAM));
        self::assertTrue(AccessScope::TEAM->isAtLeast(AccessScope::OWN));
        self::assertFalse(AccessScope::OWN->isAtLeast(AccessScope::TEAM));
        self::assertNull(AccessScope::widest([]));
        self::assertSame(AccessScope::ALL, AccessScope::widest([AccessScope::OWN, AccessScope::ALL]));
    }

    #[Test]
    public function only_creating_needs_no_record(): void
    {
        self::assertFalse(Action::CREATE->needsRecord());

        foreach ([Action::VIEW, Action::EDIT, Action::DELETE] as $action) {
            self::assertTrue($action->needsRecord());
        }
    }
}
