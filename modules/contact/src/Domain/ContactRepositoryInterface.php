<?php

declare(strict_types=1);

namespace Crm\Contact\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Der Port. Die Doctrine-Implementierung liegt in Infrastructure und wird
 * ueber einen Alias in config/services.php verdrahtet - Domain und
 * Application kennen nur dieses Interface.
 */
interface ContactRepositoryInterface
{
    public function save(Contact $contact): void;

    public function remove(Contact $contact): void;

    public function find(Uuid $id): ?Contact;

    /**
     * Freitextsuche ueber Vorname, Nachname und E-Mail.
     *
     * Firmennamen stehen nicht in dieser Tabelle - nur skalare IDs. Wer nach
     * einer Firma suchen will, loest den Namen vorher ueber
     * CompanyFinderInterface zu IDs auf und reicht sie hier herein. Das
     * Repository weiss dadurch nicht, was eine Firma ist; es filtert nur auf
     * eine Spalte.
     *
     * @param string       $query      Leerstring bedeutet: keine Einschraenkung.
     * @param list<string> $companyIds Treffer aus diesen Firmen werden
     *                                 zusaetzlich aufgenommen (ODER-verknuepft).
     *
     * @return list<Contact>
     */
    public function search(string $query, array $companyIds = [], int $limit = 50): array;

    /**
     * @param list<string> $companyIds
     *
     * @return list<Contact>
     */
    public function findByCompanyIds(array $companyIds, int $limit = 50): array;

    public function countByCompanyId(string $companyId): int;

    public function countAll(): int;
}
