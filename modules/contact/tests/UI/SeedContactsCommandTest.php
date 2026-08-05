<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\UI;

use Crm\Contact\Application\CreateContact;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use Crm\Contact\UI\Console\SeedContactsCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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
    public function it_creates_contacts_that_are_searchable(): void
    {
        [$tester, $repository] = $this->command();
        $tester->execute([]);

        self::assertCount(2, $repository->search('Nordwind'));
    }

    /**
     * @return array{0: CommandTester, 1: InMemoryContactRepository}
     */
    private function command(): array
    {
        $repository = new InMemoryContactRepository();
        $command = new SeedContactsCommand(new CreateContact($repository), $repository);

        $application = new Application();
        $application->addCommand($command);

        return [new CommandTester($application->find('contact:seed')), $repository];
    }
}
