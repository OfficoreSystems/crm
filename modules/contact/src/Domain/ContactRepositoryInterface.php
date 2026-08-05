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
     * Freitextsuche ueber Vorname, Nachname, E-Mail und Firma.
     *
     * @param string $query Leerstring bedeutet: keine Einschraenkung.
     *
     * @return list<Contact>
     */
    public function search(string $query, int $limit = 50): array;

    public function countAll(): int;
}
