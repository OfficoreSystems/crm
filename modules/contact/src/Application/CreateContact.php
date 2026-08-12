<?php

declare(strict_types=1);

namespace Crm\Contact\Application;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;

/**
 * Use-Case: einen Kontakt anlegen.
 *
 * Prueft die Firmen-ID bewusst *nicht*. Ohne installiertes company-Modul
 * antwortet der CompanyFinder aus dem Shared Kernel auf jede Anfrage mit
 * "kenne ich nicht" - eine Pflichtpruefung hier wuerde jede Zuordnung
 * unmoeglich machen, sobald das Modul fehlt. Wer geprueft zuordnen will,
 * nutzt {@see AssignContactToCompany}.
 */
final readonly class CreateContact
{
    public function __construct(
        private ContactRepositoryInterface $contacts,
    ) {
    }

    public function __invoke(CreateContactCommand $command): Contact
    {
        $contact = Contact::create(
            $command->firstName,
            $command->lastName,
            $command->email,
            $command->companyId,
        );

        $this->contacts->save($contact);

        return $contact;
    }
}
