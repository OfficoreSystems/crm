<?php

declare(strict_types=1);

namespace Crm\Contact\UI\Component;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live-Suche ueber die Kontaktliste.
 *
 * `url: true` haelt den Suchbegriff in der Query-String - damit ist ein
 * Suchergebnis teilbar und der Zurueck-Button tut, was man erwartet.
 */
#[AsLiveComponent(name: 'ContactList', template: '@ContactModule/components/ContactList.html.twig')]
final class ContactList
{
    use DefaultActionTrait;

    private const LIMIT = 50;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<Contact>
     */
    public function getContacts(): array
    {
        return $this->repository->search($this->query, self::LIMIT);
    }

    public function getTotal(): int
    {
        return $this->repository->countAll();
    }

    /**
     * Sagt dem Template, ob es "keine Treffer" oder "noch nichts angelegt"
     * anzeigen soll - zwei sehr verschiedene Zustaende.
     */
    public function isFiltered(): bool
    {
        return '' !== trim($this->query);
    }

    public function isTruncated(): bool
    {
        return \count($this->getContacts()) >= self::LIMIT;
    }
}
