<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Security;

use Crm\SharedKernel\Security\AccessScope;
use Crm\SharedKernel\Security\Action;
use Crm\SharedKernel\Security\OwnershipRegistry;
use Crm\SharedKernel\Security\RecordOwnership;
use Crm\SharedKernel\Security\RecordOwnershipInterface;
use Crm\SharedKernel\Security\RestrictedColumns;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OwnershipRegistryTest extends TestCase
{
    #[Test]
    public function it_finds_the_module_a_record_belongs_to(): void
    {
        $registry = new OwnershipRegistry([new CountingOwnership('deal', FakeDeal::class)]);

        self::assertSame('deal', $registry->moduleOf(new FakeDeal('anna', 'vertrieb')));
        self::assertTrue($registry->supports(new FakeDeal('anna', 'vertrieb')));
    }

    #[Test]
    public function an_unclaimed_record_belongs_to_no_module(): void
    {
        $registry = new OwnershipRegistry([new CountingOwnership('deal', FakeDeal::class)]);

        self::assertNull($registry->moduleOf(new \stdClass()));
        self::assertFalse($registry->supports(new \stdClass()));
    }

    #[Test]
    public function an_unclaimed_record_belongs_to_nobody(): void
    {
        // Die sichere Vorgabe: ohne Besitzangabe zaehlt nur ALL.
        $registry = new OwnershipRegistry([]);
        $ownership = $registry->ownershipOf(new \stdClass());

        self::assertNull($ownership->ownerId);
        self::assertNull($ownership->teamId);
    }

    #[Test]
    public function it_asks_each_provider_only_once_per_class(): void
    {
        // supports() liefe bei einer Liste sonst je Zeile durch alle Anbieter.
        $provider = new CountingOwnership('deal', FakeDeal::class);
        $registry = new OwnershipRegistry([$provider]);

        for ($i = 0; $i < 25; ++$i) {
            $registry->ownershipOf(new FakeDeal('anna', 'vertrieb'));
        }

        self::assertSame(1, $provider->supportsCalls);
    }

    #[Test]
    public function it_lists_the_modules_it_knows(): void
    {
        $registry = new OwnershipRegistry([
            new CountingOwnership('deal', FakeDeal::class),
            new CountingOwnership('activity', \stdClass::class),
        ]);

        self::assertSame(['deal', 'activity'], $registry->knownModules());
        self::assertSame([], (new OwnershipRegistry([]))->knownModules());
    }

    #[Test]
    public function ownership_compares_against_the_actor(): void
    {
        $anna = new FakeActor('anna', 'vertrieb', ['ROLE_USER']);
        $ownership = new RecordOwnership('anna', 'vertrieb');

        self::assertTrue($ownership->isOwnedBy($anna));
        self::assertTrue($ownership->belongsToTeamOf($anna));
        self::assertFalse($ownership->isOwnedBy(new FakeActor('bogdan', 'vertrieb', [])));
        self::assertFalse($ownership->belongsToTeamOf(new FakeActor('bogdan', 'innendienst', [])));
    }

    #[Test]
    public function a_record_without_an_owner_belongs_to_nobody(): void
    {
        $nobody = RecordOwnership::nobody();
        $anna = new FakeActor('anna', 'vertrieb', []);

        self::assertFalse($nobody->isOwnedBy($anna));
        self::assertFalse($nobody->belongsToTeamOf($anna));
    }

    #[Test]
    public function every_action_and_scope_has_a_label(): void
    {
        foreach (Action::cases() as $action) {
            self::assertNotSame('', $action->label());
        }

        foreach (AccessScope::cases() as $scope) {
            self::assertNotSame('', $scope->label());
        }
    }

    // --- Was der Sichtbarkeitsfilter aus der Registry zieht ---

    #[Test]
    public function it_collects_the_columns_the_modules_declare(): void
    {
        $registry = new OwnershipRegistry([
            new CountingOwnership('deal', FakeDeal::class, new RestrictedColumns(FakeDeal::class, 'owner_id', 'owner_team_id')),
        ]);

        $restrictions = $registry->restrictions();

        self::assertArrayHasKey(FakeDeal::class, $restrictions);
        self::assertSame('deal', $restrictions[FakeDeal::class]->module);
        self::assertSame('owner_id', $restrictions[FakeDeal::class]->ownerColumn);
        self::assertSame('owner_team_id', $restrictions[FakeDeal::class]->teamColumn);
    }

    #[Test]
    public function a_module_without_columns_contributes_nothing(): void
    {
        // Es gibt Anbieter, die nur den Voter bedienen - etwa fuer Objekte,
        // die gar nicht aus der Datenbank kommen. Die duerfen den Filter nicht
        // mit einer leeren Einschraenkung fuettern.
        $registry = new OwnershipRegistry([new CountingOwnership('deal', FakeDeal::class)]);

        self::assertSame([], $registry->restrictions());
    }

    #[Test]
    public function without_any_module_there_is_nothing_to_restrict(): void
    {
        // Der Zustand nach dem Abhaengen aller Module: der Filter erzeugt dann
        // keine Bedingung. Das ist richtig - es gibt auch keine Tabellen mehr,
        // auf die er sich beziehen koennte.
        self::assertSame([], (new OwnershipRegistry([]))->restrictions());
    }
}

final class CountingOwnership implements RecordOwnershipInterface
{
    public int $supportsCalls = 0;

    /**
     * @param class-string $supportedClass
     */
    public function __construct(
        private readonly string $module,
        private readonly string $supportedClass,
        private readonly ?RestrictedColumns $columns = null,
    ) {
    }

    public function module(): string
    {
        return $this->module;
    }

    public function supports(object $record): bool
    {
        ++$this->supportsCalls;

        return $record instanceof $this->supportedClass;
    }

    public function ownershipOf(object $record): RecordOwnership
    {
        \assert($record instanceof FakeDeal);

        return new RecordOwnership($record->ownerId, $record->teamId);
    }

    public function restrictedColumns(): ?RestrictedColumns
    {
        return $this->columns;
    }
}
