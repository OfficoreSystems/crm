<?php

declare(strict_types=1);

namespace Crm\Activity\Tests\UI;

use Crm\Activity\Application\LogActivity;
use Crm\Activity\Domain\Activity;
use Crm\Activity\Tests\Double\InMemoryActivityRepository;
use Crm\Activity\UI\Console\SeedActivitiesCommand;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use Crm\SharedKernel\User\NullUserFinder;
use Crm\SharedKernel\User\UserFinderInterface;
use Crm\SharedKernel\User\UserSummary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class SeedActivitiesCommandTest extends TestCase
{
    #[Test]
    public function it_spreads_entries_across_every_registered_type(): void
    {
        // Der Befehl benennt kein Modul - er fragt die Registry, welche Typen
        // es gerade gibt.
        [$tester, $activities] = $this->command($this->registry());

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $types = array_unique(array_map(
            static fn (Activity $a): string => $a->subject()->type,
            $activities->findRecent(),
        ));
        sort($types);

        self::assertSame(['company', 'contact'], $types);
    }

    #[Test]
    public function it_takes_at_most_three_subjects_per_type(): void
    {
        // Genug fuer eine sichtbare Timeline, ohne die Datenbank zu fluten.
        [$tester, $activities] = $this->command($this->registry());
        $tester->execute([]);

        self::assertSame(3, \count($activities->findRecent('contact')));
        self::assertSame(1, \count($activities->findRecent('company')));
    }

    #[Test]
    public function it_produces_a_mix_of_activity_types(): void
    {
        [$tester, $activities] = $this->command($this->registry());
        $tester->execute([]);

        $types = array_unique(array_map(
            static fn (Activity $a): string => $a->type()->value,
            $activities->findRecent(),
        ));

        self::assertGreaterThan(1, \count($types));
    }

    #[Test]
    public function it_does_nothing_when_activities_already_exist(): void
    {
        [$tester, $activities] = $this->command($this->registry());
        $tester->execute([]);
        $before = $activities->countAll();

        $tester->execute([]);

        self::assertSame($before, $activities->countAll());
        self::assertStringContainsString('bereits Aktivitaeten vorhanden', $tester->getDisplay());
    }

    #[Test]
    public function without_any_resolver_it_warns_instead_of_failing(): void
    {
        // Der Zustand direkt nach dem Entfernen aller Subjekt-Module.
        [$tester, $activities] = $this->command(new SubjectResolverRegistry([]));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(0, $activities->countAll());
        self::assertStringContainsString('Kein aufloesbares Subjekt', $tester->getDisplay());
    }

    // --- Autoren ---

    #[Test]
    public function the_entries_are_spread_over_several_authors(): void
    {
        // Gehoerte die ganze Timeline einem Benutzer, saehe eine kaputte
        // Filterung genauso aus wie eine funktionierende: alles oder nichts.
        $vera = new UserSummary((string) Uuid::v7(), 'Vera', 'v@officore.test', (string) Uuid::v7());
        $ingo = new UserSummary((string) Uuid::v7(), 'Ingo', 'i@officore.test', (string) Uuid::v7());

        [$tester, $activities] = $this->command($this->registry(), new FakeUsers([$vera, $ingo]));
        $tester->execute([]);

        $authors = [];

        foreach ($activities->findRecent(limit: 100) as $activity) {
            $authors[(string) $activity->authorId()] = true;
        }

        self::assertCount(2, $authors, 'Beide Benutzer sollen Eintraege haben.');
    }

    #[Test]
    public function an_author_always_brings_their_team_along(): void
    {
        // Ohne Team faellt der Eintrag im Filter auf den engsten Scope zurueck
        // und waere fuer die Kollegen unsichtbar.
        $vera = new UserSummary((string) Uuid::v7(), 'Vera', 'v@officore.test', (string) Uuid::v7());

        [$tester, $activities] = $this->command($this->registry(), new FakeUsers([$vera]));
        $tester->execute([]);

        foreach ($activities->findRecent(limit: 100) as $activity) {
            self::assertSame($vera->id, (string) $activity->authorId());
            self::assertSame($vera->teamId, (string) $activity->authorTeamId());
        }
    }

    #[Test]
    public function a_user_without_a_team_is_not_used_as_an_author(): void
    {
        // Ein teamloser Autor macht seine Eintraege fuer alle anderen
        // unsichtbar - als Beispieldaten waere das nur verwirrend.
        $solo = new UserSummary((string) Uuid::v7(), 'Solo', 's@officore.test', null);

        [$tester, $activities] = $this->command($this->registry(), new FakeUsers([$solo]));
        $tester->execute([]);

        self::assertGreaterThan(0, $activities->countAll());
        self::assertNull($activities->findRecent(limit: 1)[0]->authorId());
        self::assertStringContainsString('Kein Autor zugeordnet', $tester->getDisplay());
    }

    /**
     * @return array{0: CommandTester, 1: InMemoryActivityRepository}
     */
    private function command(SubjectResolverRegistry $registry, ?UserFinderInterface $users = null): array
    {
        $activities = new InMemoryActivityRepository();

        $application = new Application();
        $application->addCommand(new SeedActivitiesCommand(
            new LogActivity($activities, $registry),
            $activities,
            $registry,
            $users ?? new NullUserFinder(),
        ));

        return [new CommandTester($application->find('activity:seed')), $activities];
    }

    private function registry(): SubjectResolverRegistry
    {
        return new SubjectResolverRegistry([
            new CountingResolver('contact', 'Kontakt', ['a' => 'Anna', 'b' => 'Bogdan', 'c' => 'Clara', 'd' => 'Deniz']),
            new CountingResolver('company', 'Firma', ['x' => 'Nordwind Logistik']),
        ]);
    }
}

/**
 * Ein Ersatz fuer das user-Modul.
 *
 * Bewusst eine eigene Klasse und keine aus einem anderen Modul: Testdoubles
 * ueber Modulgrenzen zu teilen waere genau die Kopplung, die dieses Projekt
 * vermeiden will.
 */
final class FakeUsers implements UserFinderInterface
{
    /**
     * @param list<UserSummary> $users
     */
    public function __construct(private readonly array $users)
    {
    }

    public function find(string $id): ?UserSummary
    {
        return $this->findMany([$id])[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        $found = [];

        foreach ($this->users as $user) {
            if (\in_array($user->id, $ids, true)) {
                $found[$user->id] = $user;
            }
        }

        return $found;
    }

    public function findAllActive(): array
    {
        return $this->users;
    }
}
