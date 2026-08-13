<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\UI;

use Crm\Deal\Application\CreateDeal;
use Crm\Deal\Domain\Stage;
use Crm\Deal\Tests\Double\InMemoryDealRepository;
use Crm\Deal\UI\Console\SeedDealsCommand;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\CompanySummary;
use Crm\SharedKernel\Company\NullCompanyFinder;
use Crm\SharedKernel\User\NullUserFinder;
use Crm\SharedKernel\User\UserFinderInterface;
use Crm\SharedKernel\User\UserSummary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class SeedDealsCommandTest extends TestCase
{
    #[Test]
    public function it_seeds_an_empty_database(): void
    {
        [$tester, $deals] = $this->command();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(9, $deals->countAll());
    }

    #[Test]
    public function it_does_nothing_when_deals_already_exist(): void
    {
        [$tester, $deals] = $this->command();
        $tester->execute([]);

        $tester->execute([]);

        self::assertSame(9, $deals->countAll());
        self::assertStringContainsString('bereits Verkaufschancen vorhanden', $tester->getDisplay());
    }

    #[Test]
    public function the_samples_cover_open_and_closed_stages(): void
    {
        // Sonst waere die Gewinnquote auf dem Board immer null und man saehe
        // nicht, ob die Berechnung ueberhaupt funktioniert.
        [$tester, $deals] = $this->command();
        $tester->execute([]);

        self::assertGreaterThan(0, $deals->countByStage(Stage::WON));
        self::assertGreaterThan(0, $deals->countByStage(Stage::LOST));
        self::assertGreaterThan(0, $deals->countByStage(Stage::LEAD));
    }

    #[Test]
    public function it_links_deals_to_matching_companies(): void
    {
        $nordwind = Uuid::v7();
        $finder = new FakeCompanies([new CompanySummary((string) $nordwind, 'Nordwind Logistik')]);

        [$tester, $deals] = $this->command($finder);
        $tester->execute([]);

        $linked = array_filter(
            $deals->search(''),
            static fn ($d): bool => $nordwind->equals($d->companyId()),
        );

        self::assertCount(2, $linked, 'Zwei Beispielchancen gehoeren zu Nordwind.');
        self::assertStringContainsString('einer Firma zugeordnet', $tester->getDisplay());
    }

    #[Test]
    public function it_works_without_a_company_module_at_all(): void
    {
        [$tester, $deals] = $this->command(new NullCompanyFinder());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(9, $deals->countAll());
        self::assertStringContainsString('Keine Firma zugeordnet', $tester->getDisplay());
    }

    // --- Besitzverhaeltnisse ---

    #[Test]
    public function it_prefers_an_ordinary_user_over_the_administrator(): void
    {
        // Sonst gehoerten die Beispieldaten dem Administrator - und der sieht
        // ohnehin alles. Von den Sichtbarkeitsregeln waere dann nichts zu
        // bemerken, und das faellt beim Ausprobieren niemandem auf.
        $admin = new UserSummary((string) Uuid::v7(), 'Chefin', 'admin@officore.test', (string) Uuid::v7());
        $vera = new UserSummary((string) Uuid::v7(), 'Vera', 'vertrieb@officore.test', (string) Uuid::v7());

        [$tester, $deals] = $this->command(users: new FakeUsers([$admin, $vera]));
        $tester->execute([]);

        foreach ($deals->search('') as $deal) {
            self::assertSame($vera->id, (string) $deal->ownerId());
            self::assertSame($vera->teamId, (string) $deal->ownerTeamId());
        }

        self::assertStringContainsString('Vera', $tester->getDisplay());
    }

    #[Test]
    public function without_a_user_module_the_deals_stay_ownerless_and_it_says_so(): void
    {
        // Der Zustand nach dem Abhaengen des user-Moduls: die Chancen
        // entstehen trotzdem, aber ohne Besitzer greift kein Filter. Eine
        // stille Null waere hier die schlechtere Antwort.
        [$tester, $deals] = $this->command(users: new NullUserFinder());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(9, $deals->countAll());

        foreach ($deals->search('') as $deal) {
            self::assertNull($deal->ownerId());
        }

        self::assertStringContainsString('Kein Besitzer zugeordnet', $tester->getDisplay());
    }

    #[Test]
    public function a_user_without_a_team_is_still_better_than_nobody(): void
    {
        $einzelkaempfer = new UserSummary((string) Uuid::v7(), 'Solo', 'solo@officore.test', null);

        [$tester, $deals] = $this->command(users: new FakeUsers([$einzelkaempfer]));
        $tester->execute([]);

        $deal = $deals->search('')[0];

        self::assertSame($einzelkaempfer->id, (string) $deal->ownerId());
        self::assertNull($deal->ownerTeamId());
    }

    /**
     * @return array{0: CommandTester, 1: InMemoryDealRepository}
     */
    private function command(?CompanyFinderInterface $companies = null, ?UserFinderInterface $users = null): array
    {
        $deals = new InMemoryDealRepository();

        $application = new Application();
        $application->addCommand(new SeedDealsCommand(
            new CreateDeal($deals),
            $deals,
            $companies ?? new FakeCompanies([]),
            $users ?? new NullUserFinder(),
        ));

        return [new CommandTester($application->find('deal:seed')), $deals];
    }
}

/**
 * Ein Ersatz fuer das user-Modul. Liefert genau das, was der Finder-Vertrag
 * verspricht - mehr braucht der Seed nicht zu wissen.
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
