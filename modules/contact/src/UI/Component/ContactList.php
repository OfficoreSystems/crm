<?php

declare(strict_types=1);

namespace Crm\Contact\UI\Component;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Crm\SharedKernel\Company\CompanyFinderInterface;
use Crm\SharedKernel\Company\CompanySummary;
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

    /**
     * @var list<Contact>|null
     */
    private ?array $cachedContacts = null;

    /**
     * @var array<string, CompanySummary>|null
     */
    private ?array $cachedCompanies = null;

    public function __construct(
        private readonly ContactRepositoryInterface $repository,
        private readonly CompanyFinderInterface $companies,
    ) {
    }

    /**
     * @return list<Contact>
     */
    public function getContacts(): array
    {
        if (null !== $this->cachedContacts) {
            return $this->cachedContacts;
        }

        // Zwei Abfragen statt eines Joins ueber die Modulgrenze: erst den
        // Suchbegriff gegen Firmennamen aufloesen, dann mit den gefundenen IDs
        // die eigene Tabelle filtern. Wer "Nordwind" tippt, findet so auch
        // Kontakte, die selbst nicht so heissen.
        $companyIds = array_map(
            static fn (CompanySummary $company): string => $company->id,
            $this->companies->searchByName($this->query),
        );

        return $this->cachedContacts = $this->repository->search($this->query, $companyIds, self::LIMIT);
    }

    /**
     * Die Firmen der angezeigten Kontakte, indiziert nach ID.
     *
     * Ein einziger Aufruf fuer die ganze Liste. Wuerde das Template je Zeile
     * nachschlagen, haette man ein N+1 - und zwar ueber eine Modulgrenze
     * hinweg, wo es besonders teuer ist.
     *
     * @return array<string, CompanySummary>
     */
    public function getCompanies(): array
    {
        if (null !== $this->cachedCompanies) {
            return $this->cachedCompanies;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (Contact $contact): ?string => $contact->companyId()?->toString(),
            $this->getContacts(),
        ))));

        return $this->cachedCompanies = [] === $ids ? [] : $this->companies->findMany($ids);
    }

    /**
     * Firmenname zu einem Kontakt, oder null.
     *
     * Null bedeutet zweierlei und beides ist in Ordnung: der Kontakt hat
     * keine Firma, oder die hinterlegte ID laesst sich nicht aufloesen - weil
     * die Firma geloescht wurde oder das company-Modul gar nicht installiert
     * ist. Ohne Fremdschluessel ueber Modulgrenzen ist das ein normaler
     * Zustand, kein Fehler.
     */
    public function companyNameFor(Contact $contact): ?string
    {
        $id = $contact->companyId()?->toString();

        if (null === $id) {
            return null;
        }

        return $this->getCompanies()[$id]->name ?? null;
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
