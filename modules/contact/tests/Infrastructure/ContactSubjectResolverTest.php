<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Infrastructure;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Infrastructure\SharedKernel\ContactSubjectResolver;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Macht Kontakte als polymorphes Subjekt verweisbar - die Seite, auf der
 * dieses Modul den Extension-Point bedient.
 */
final class ContactSubjectResolverTest extends TestCase
{
    private InMemoryContactRepository $contacts;
    private ContactSubjectResolver $resolver;

    protected function setUp(): void
    {
        $this->contacts = new InMemoryContactRepository();
        $this->resolver = new ContactSubjectResolver($this->contacts);
    }

    #[Test]
    public function it_declares_its_type(): void
    {
        self::assertSame('contact', $this->resolver->type());
        self::assertSame(ContactSubjectResolver::TYPE, $this->resolver->type());
        self::assertNotSame('', $this->resolver->typeLabel());
    }

    #[Test]
    public function it_resolves_ids_to_labelled_and_linkable_subjects(): void
    {
        $this->contacts->save($anna = Contact::create('Anna', 'Berger'));

        $resolved = $this->resolver->resolve([(string) $anna->id()]);

        $subject = $resolved[(string) $anna->id()];
        self::assertSame('Anna Berger', $subject->label);
        self::assertSame('contact', $subject->type);
        self::assertTrue($subject->isLinkable());
    }

    #[Test]
    public function it_resolves_several_ids_in_one_call(): void
    {
        // Die Signatur nimmt eine Liste, damit die Registry buendeln kann.
        $this->contacts->save($anna = Contact::create('Anna', 'Berger'));
        $this->contacts->save($erik = Contact::create('Erik', 'Lindqvist'));

        $resolved = $this->resolver->resolve([(string) $anna->id(), (string) $erik->id()]);

        self::assertCount(2, $resolved);
    }

    #[Test]
    public function unknown_and_malformed_ids_are_skipped(): void
    {
        $this->contacts->save($anna = Contact::create('Anna', 'Berger'));

        $resolved = $this->resolver->resolve([(string) $anna->id(), (string) Uuid::v7(), 'keine-uuid', '']);

        self::assertCount(1, $resolved);
    }

    #[Test]
    public function it_offers_candidates_for_a_picker(): void
    {
        $this->contacts->save(Contact::create('Anna', 'Berger'));
        $this->contacts->save(Contact::create('Erik', 'Lindqvist'));

        self::assertCount(1, $this->resolver->search('Berger'));
        self::assertCount(0, $this->resolver->search('Gibtsnicht'));
    }

    #[Test]
    public function an_empty_query_returns_the_first_entries_not_none(): void
    {
        // Das erwartete Verhalten eines Auswahlfelds beim Oeffnen.
        $this->contacts->save(Contact::create('Anna', 'Berger'));
        $this->contacts->save(Contact::create('Erik', 'Lindqvist'));

        self::assertCount(2, $this->resolver->search(''));
        self::assertCount(1, $this->resolver->search('', 1), 'Das Limit greift');
    }
}
