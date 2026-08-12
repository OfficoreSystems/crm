<?php

declare(strict_types=1);

namespace Crm\Contact\Application;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Crm\Contact\Domain\UnknownCompany;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Use-Case: einen Kontakt einer Firma zuordnen.
 *
 * Hier - und nur hier - wird geprueft, ob die Firma existiert. Die Datenbank
 * kann das nicht: ueber Modulgrenzen gibt es keinen Fremdschluessel. Die
 * Pruefung laeuft ueber CompanyFinderInterface, also ohne dass dieses Modul
 * das company-Modul kennt.
 */
final readonly class AssignContactToCompany
{
    public function __construct(
        private ContactRepositoryInterface $contacts,
        private CompanyFinderInterface $companies,
    ) {
    }

    public function __invoke(Contact $contact, ?Uuid $companyId): Contact
    {
        if (null !== $companyId && !$this->companies->exists((string) $companyId)) {
            throw UnknownCompany::withId((string) $companyId);
        }

        $contact->assignToCompany($companyId);
        $this->contacts->save($contact);

        return $contact;
    }
}
