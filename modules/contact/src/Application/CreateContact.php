<?php

declare(strict_types=1);

namespace Crm\Contact\Application;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;

/**
 * Use-Case: einen Kontakt anlegen.
 *
 * Invokable, damit ein Use-Case genau eine Sache tut und der Aufrufer nicht
 * raten muss, welche der zwoelf Methoden eines Services gemeint ist.
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
            $command->company,
        );

        $this->contacts->save($contact);

        return $contact;
    }
}
