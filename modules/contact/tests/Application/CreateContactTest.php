<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Application;

use Crm\Contact\Application\CreateContact;
use Crm\Contact\Application\CreateContactCommand;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

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

        $contact = $createContact(new CreateContactCommand('Anna', 'Berger', 'anna@example.test', Uuid::v7()));

        self::assertSame(1, $repository->countAll());
        self::assertSame($contact, $repository->find($contact->id()));
    }

    #[Test]
    public function it_accepts_a_company_id_without_checking_it(): void
    {
        // Absicht: ohne installiertes company-Modul antwortet der Finder auf
        // jede Anfrage mit "kenne ich nicht". Eine Pflichtpruefung hier wuerde
        // jede Zuordnung unmoeglich machen. Geprueft wird in
        // AssignContactToCompany.
        $repository = new InMemoryContactRepository();

        $contact = (new CreateContact($repository))(
            new CreateContactCommand('Anna', 'Berger', companyId: $unknown = Uuid::v7()),
        );

        self::assertTrue($unknown->equals($contact->companyId()));
    }

    #[Test]
    public function a_contact_without_a_company_stays_without_one(): void
    {
        $contact = (new CreateContact(new InMemoryContactRepository()))(
            new CreateContactCommand('Anna', 'Berger'),
        );

        self::assertNull($contact->companyId());
        self::assertFalse($contact->belongsToACompany());
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
