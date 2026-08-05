<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Domain;

use Crm\Contact\Domain\Contact;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class ContactTest extends TestCase
{
    #[Test]
    public function it_composes_a_full_name(): void
    {
        $contact = Contact::create('Anna', 'Berger');

        self::assertSame('Anna Berger', $contact->fullName());
    }

    #[Test]
    public function it_trims_names(): void
    {
        $contact = Contact::create('  Anna  ', "Berger\t");

        self::assertSame('Anna', $contact->firstName());
        self::assertSame('Berger', $contact->lastName());
    }

    #[Test]
    public function it_normalises_blank_optionals_to_null(): void
    {
        // Sonst stehen '' und null nebeneinander in der Spalte und jede
        // Abfrage muss beide Faelle kennen.
        $contact = Contact::create('Anna', 'Berger', '   ', '');

        self::assertNull($contact->email());
        self::assertNull($contact->company());
    }

    #[Test]
    public function it_rejects_a_blank_first_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Contact::create('   ', 'Berger');
    }

    #[Test]
    public function it_rejects_a_blank_last_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Contact::create('Anna', '');
    }

    #[Test]
    public function it_assigns_a_sortable_uuid(): void
    {
        // v7 ist zeitgeordnet - das haelt den Primaerindex beim Einfuegen
        // kompakt, anders als ein zufaelliges v4.
        $contact = Contact::create('Anna', 'Berger');

        self::assertInstanceOf(UuidV7::class, $contact->id());
    }

    #[Test]
    public function its_ids_sort_in_creation_order(): void
    {
        $first = Contact::create('Anna', 'Berger');
        $second = Contact::create('Bogdan', 'Petrov');

        self::assertLessThan(0, $first->id()->compare($second->id()));
    }

    #[Test]
    public function it_can_be_renamed(): void
    {
        $contact = Contact::create('Anna', 'Berger');
        $contact->rename('Anna', 'Berger-Vogel');

        self::assertSame('Anna Berger-Vogel', $contact->fullName());
    }

    #[Test]
    public function renaming_rejects_a_blank_value(): void
    {
        $contact = Contact::create('Anna', 'Berger');

        $this->expectException(\InvalidArgumentException::class);

        $contact->rename('Anna', '  ');
    }

    #[Test]
    public function it_can_change_its_email(): void
    {
        $contact = Contact::create('Anna', 'Berger', 'alt@example.test');

        $contact->changeEmail('  neu@example.test  ');

        self::assertSame('neu@example.test', $contact->email());
    }

    #[Test]
    public function clearing_the_email_stores_null_not_an_empty_string(): void
    {
        $contact = Contact::create('Anna', 'Berger', 'alt@example.test');

        $contact->changeEmail('');

        self::assertNull($contact->email());
    }

    #[Test]
    public function it_can_change_its_company(): void
    {
        $contact = Contact::create('Anna', 'Berger');

        $contact->changeCompany(' Nordwind Logistik ');
        self::assertSame('Nordwind Logistik', $contact->company());

        $contact->changeCompany(null);
        self::assertNull($contact->company());
    }

    #[Test]
    public function it_keeps_the_creation_timestamp_it_was_given(): void
    {
        $moment = new \DateTimeImmutable('2026-03-01 09:15:00');

        $contact = Contact::create('Anna', 'Berger', createdAt: $moment);

        self::assertSame($moment, $contact->createdAt());
    }
}
