<?php

declare(strict_types=1);

namespace Crm\Activity\Tests\UI;

use Crm\Activity\Application\LogActivity;
use Crm\Activity\Domain\Activity;
use Crm\Activity\Tests\Double\InMemoryActivityRepository;
use Crm\Activity\UI\Console\SeedActivitiesCommand;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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

    /**
     * @return array{0: CommandTester, 1: InMemoryActivityRepository}
     */
    private function command(SubjectResolverRegistry $registry): array
    {
        $activities = new InMemoryActivityRepository();

        $application = new Application();
        $application->addCommand(new SeedActivitiesCommand(
            new LogActivity($activities, $registry),
            $activities,
            $registry,
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
