<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Application;

use Crm\Contact\Application\CreateContact;
use Crm\Contact\Application\CreateContactCommand;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Der Use-Case laeuft hier komplett ohne Doctrine - genau dafuer ist der Port
 * da. Wenn dieser Test eine Datenbank braucht, ist die Schichtung kaputt.
 */
final class CreateContactTest extends TestCase
{
    #[Test]
    public function it_persists_the_new_contact(): void
    {
        $repository = new InMemoryContactRepository();
        $createContact = new CreateContact($repository);

        $contact = $createContact(new CreateContactCommand('Anna', 'Berger', 'anna@example.test', 'Nordwind'));

        self::assertSame(1, $repository->countAll());
        self::assertSame($contact, $repository->find($contact->id()));
    }

    #[Test]
    public function it_returns_the_created_contact(): void
    {
        $createContact = new CreateContact(new InMemoryContactRepository());

        $contact = $createContact(new CreateContactCommand('Bogdan', 'Petrov'));

        self::assertSame('Bogdan Petrov', $contact->fullName());
        self::assertNull($contact->email());
    }

    #[Test]
    public function it_rejects_an_incomplete_command(): void
    {
        $createContact = new CreateContact($repository = new InMemoryContactRepository());

        try {
            $createContact(new CreateContactCommand('', 'Berger'));
            self::fail('Ein leerer Vorname haette abgelehnt werden muessen.');
        } catch (\InvalidArgumentException) {
            // Nichts darf gespeichert worden sein.
            self::assertSame(0, $repository->countAll());
        }
    }
}
