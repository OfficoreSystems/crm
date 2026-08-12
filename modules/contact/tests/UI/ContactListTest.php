<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\UI;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Tests\Double\CountingCompanyFinder;
use Crm\Contact\Tests\Double\FakeCompanyFinder;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use Crm\Contact\UI\Component\ContactList;
use Crm\SharedKernel\Company\CompanySummary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ContactListTest extends TestCase
{
    #[Test]
    public function it_lists_everything_without_a_query(): void
    {
        $component = $this->componentWith(3);

        self::assertCount(3, $component->getContacts());
        self::assertSame(3, $component->getTotal());
    }

    #[Test]
    public function it_narrows_the_list_down_to_the_query(): void
    {
        $component = $this->componentWith(3);
        $component->query = 'Nachname1';

        self::assertCount(1, $component->getContacts());
        // Der Gesamtwert bleibt - das Template zeigt "1 von 3".
        self::assertSame(3, $component->getTotal());
    }

    #[Test]
    public function an_empty_query_does_not_count_as_filtered(): void
    {
        self::assertFalse($this->componentWith(1)->isFiltered());
    }

    #[Test]
    public function a_whitespace_query_does_not_count_as_filtered(): void
    {
        // Sonst zeigt das Template "Kein Treffer fuer '   '" statt
        // "Noch keine Kontakte angelegt".
        $component = $this->componentWith(1);
        $component->query = '   ';

        self::assertFalse($component->isFiltered());
    }

    #[Test]
    public function a_real_query_counts_as_filtered(): void
    {
        $component = $this->componentWith(1);
        $component->query = 'Berger';

        self::assertTrue($component->isFiltered());
    }

    #[Test]
    public function a_short_list_is_not_truncated(): void
    {
        self::assertFalse($this->componentWith(5)->isTruncated());
    }

    #[Test]
    public function a_full_page_is_reported_as_truncated(): void
    {
        // Bei genau LIMIT Treffern kann niemand wissen, ob noch mehr kommt.
        self::assertTrue($this->componentWith(50)->isTruncated());
    }

    // ------------------------------------------------ Verknuepfung zur Firma

    #[Test]
    public function searching_a_company_name_finds_its_contacts(): void
    {
        // Der Kern der modulübergreifenden Suche: keiner dieser Kontakte
        // heisst "Nordwind". Der Begriff wird ueber den CompanyFinder zu IDs
        // aufgeloest, mit denen die eigene Tabelle gefiltert wird.
        [$component] = $this->componentWithCompany();
        $component->query = 'Nordwind';

        $names = array_map(static fn (Contact $c): string => $c->lastName(), $component->getContacts());

        self::assertSame(['Berger'], $names);
    }

    #[Test]
    public function it_resolves_the_company_name_for_display(): void
    {
        [$component, $contactWithCompany] = $this->componentWithCompany();

        self::assertSame('Nordwind Logistik', $component->companyNameFor($contactWithCompany));
    }

    #[Test]
    public function a_contact_without_a_company_resolves_to_null(): void
    {
        [$component, , $contactWithout] = $this->componentWithCompany();

        self::assertNull($component->companyNameFor($contactWithout));
    }

    #[Test]
    public function an_unresolvable_company_id_resolves_to_null_instead_of_failing(): void
    {
        // Ohne Fremdschluessel ueber Modulgrenzen ist eine verwaiste ID ein
        // normaler Zustand: die Firma wurde geloescht, oder das
        // company-Modul ist gar nicht installiert.
        $repository = new InMemoryContactRepository();
        $repository->save($orphan = Contact::create('Anna', 'Berger', companyId: Uuid::v7()));

        $component = new ContactList($repository, new FakeCompanyFinder());

        self::assertNull($component->companyNameFor($orphan));
    }

    #[Test]
    public function it_resolves_all_companies_in_a_single_lookup(): void
    {
        // Wuerde je Zeile nachgeschlagen, haette man ein N+1 ueber eine
        // Modulgrenze. Der zweite Aufruf muss aus dem Cache kommen.
        $companyId = Uuid::v7();
        $repository = new InMemoryContactRepository();
        $repository->save(Contact::create('Anna', 'Berger', companyId: $companyId));
        $repository->save(Contact::create('Deniz', 'Yilmaz', companyId: $companyId));

        $finder = new CountingCompanyFinder();
        $finder->add(new CompanySummary((string) $companyId, 'Nordwind Logistik'));
        $component = new ContactList($repository, $finder);

        foreach ($component->getContacts() as $contact) {
            $component->companyNameFor($contact);
        }

        self::assertSame(1, $finder->findManyCalls);
    }

    /**
     * @return array{0: ContactList, 1: Contact, 2: Contact}
     */
    private function componentWithCompany(): array
    {
        $companyId = Uuid::v7();

        $repository = new InMemoryContactRepository();
        $repository->save($withCompany = Contact::create('Anna', 'Berger', companyId: $companyId));
        $repository->save($without = Contact::create('Erik', 'Lindqvist'));

        $finder = new FakeCompanyFinder();
        $finder->add(new CompanySummary((string) $companyId, 'Nordwind Logistik', 'Logistik', 'Hamburg'));

        return [new ContactList($repository, $finder), $withCompany, $without];
    }

    private function componentWith(int $count): ContactList
    {
        $repository = new InMemoryContactRepository();

        for ($i = 1; $i <= $count; ++$i) {
            $repository->save(Contact::create('Vorname'.$i, 'Nachname'.$i));
        }

        return new ContactList($repository, new FakeCompanyFinder());
    }
}
