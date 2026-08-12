<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Application;

use Crm\Contact\Application\AssignContactToCompany;
use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\UnknownCompany;
use Crm\Contact\Tests\Double\FakeCompanyFinder;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use Crm\SharedKernel\Company\CompanySummary;
use Crm\SharedKernel\Company\NullCompanyFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Die einzige Stelle, an der die Gueltigkeit einer Firmen-ID geprueft wird.
 * Ein Fremdschluessel kann das nicht - ueber Modulgrenzen gibt es keinen.
 */
final class AssignContactToCompanyTest extends TestCase
{
    #[Test]
    public function it_assigns_a_known_company(): void
    {
        $companyId = Uuid::v7();
        $contacts = new InMemoryContactRepository();
        $contacts->save($contact = Contact::create('Anna', 'Berger'));

        $finder = new FakeCompanyFinder();
        $finder->add(new CompanySummary((string) $companyId, 'Nordwind Logistik'));

        (new AssignContactToCompany($contacts, $finder))($contact, $companyId);

        self::assertTrue($companyId->equals($contacts->find($contact->id())?->companyId()));
    }

    #[Test]
    public function it_rejects_an_unknown_company(): void
    {
        $contacts = new InMemoryContactRepository();
        $contacts->save($contact = Contact::create('Anna', 'Berger'));

        $this->expectException(UnknownCompany::class);

        (new AssignContactToCompany($contacts, new FakeCompanyFinder()))($contact, Uuid::v7());
    }

    #[Test]
    public function a_rejected_assignment_leaves_the_contact_untouched(): void
    {
        $known = Uuid::v7();
        $contacts = new InMemoryContactRepository();
        $contacts->save($contact = Contact::create('Anna', 'Berger', companyId: $known));

        $finder = new FakeCompanyFinder();
        $finder->add(new CompanySummary((string) $known, 'Nordwind Logistik'));

        try {
            (new AssignContactToCompany($contacts, $finder))($contact, Uuid::v7());
            self::fail('Eine unbekannte Firma haette abgelehnt werden muessen.');
        } catch (UnknownCompany) {
            self::assertTrue($known->equals($contact->companyId()));
        }
    }

    #[Test]
    public function null_releases_the_assignment_without_a_lookup(): void
    {
        // Wichtig: das Loesen der Zuordnung darf auch dann funktionieren,
        // wenn das company-Modul gar nicht mehr installiert ist.
        $contacts = new InMemoryContactRepository();
        $contacts->save($contact = Contact::create('Anna', 'Berger', companyId: Uuid::v7()));

        (new AssignContactToCompany($contacts, new NullCompanyFinder()))($contact, null);

        self::assertNull($contacts->find($contact->id())?->companyId());
    }
}
