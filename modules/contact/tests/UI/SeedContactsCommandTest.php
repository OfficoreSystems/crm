<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\UI;

use Crm\Contact\Application\CreateContact;
use Crm\Contact\Tests\Double\FakeCompanyFinder;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use Crm\Contact\UI\Console\SeedContactsCommand;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\CompanySummary;
use Crm\SharedKernel\Company\NullCompanyFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class SeedContactsCommandTest extends TestCase
{
    #[Test]
    public function it_seeds_an_empty_database(): void
    {
        [$tester, $repository] = $this->command();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(8, $repository->countAll());
        self::assertStringContainsString('8 Beispielkontakte angelegt', $tester->getDisplay());
    }

    #[Test]
    public function it_does_nothing_when_contacts_already_exist(): void
    {
        // Wichtig, weil `make fresh` den Befehl bei jedem Lauf ausfuehrt -
        // ohne die Bremse haette man nach dreimal fresh 24 Kontakte.
        [$tester, $repository] = $this->command();
        $tester->execute([]);

        $tester->execute([]);

        self::assertSame(8, $repository->countAll());
        self::assertStringContainsString('bereits Kontakte vorhanden', $tester->getDisplay());
    }

    #[Test]
    public function it_links_contacts_to_matching_companies(): void
    {
        $nordwind = Uuid::v7();
        $finder = new FakeCompanyFinder();
        $finder->add(new CompanySummary((string) $nordwind, 'Nordwind Logistik'));

        [$tester, $repository] = $this->command($finder);
        $tester->execute([]);

        // Anna Berger und Deniz Yilmaz gehoeren laut Beispieldaten zu Nordwind.
        self::assertSame(2, $repository->countByCompanyId((string) $nordwind));
        self::assertStringContainsString('einer Firma zugeordnet', $tester->getDisplay());
    }

    #[Test]
    public function contacts_without_a_matching_company_stay_unlinked(): void
    {
        $finder = new FakeCompanyFinder();
        $finder->add(new CompanySummary((string) Uuid::v7(), 'Nordwind Logistik'));

        [$tester, $repository] = $this->command($finder);
        $tester->execute([]);

        $unlinked = array_filter(
            $repository->search(''),
            static fn ($c): bool => !$c->belongsToACompany(),
        );

        self::assertNotEmpty($unlinked, 'Erik Lindqvist und Hanna Vogel haben keine Firma.');
    }

    #[Test]
    public function it_works_without_a_company_module_at_all(): void
    {
        // Der NullCompanyFinder ist die Vorgabe, solange kein company-Modul
        // installiert ist. Der Seed muss dann trotzdem durchlaufen.
        [$tester, $repository] = $this->command(new NullCompanyFinder());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(8, $repository->countAll());
        self::assertStringContainsString('Keine Firma zugeordnet', $tester->getDisplay());
    }

    /**
     * @return array{0: CommandTester, 1: InMemoryContactRepository}
     */
    private function command(?CompanyFinderInterface $finder = null): array
    {
        $repository = new InMemoryContactRepository();
        $command = new SeedContactsCommand(
            new CreateContact($repository),
            $repository,
            $finder ?? new FakeCompanyFinder(),
        );

        $application = new Application();
        $application->addCommand($command);

        return [new CommandTester($application->find('contact:seed')), $repository];
    }
}
