<?php

declare(strict_types=1);

namespace Crm\Deal\Domain;

use Symfony\Component\Uid\Uuid;

interface DealRepositoryInterface
{
    public function save(Deal $deal): void;

    public function remove(Deal $deal): void;

    public function find(Uuid $id): ?Deal;

    /**
     * Freitextsuche ueber den Titel, optional erweitert um Firmen und
     * Kontakte. Wie bei contact werden die IDs von aussen hereingereicht -
     * Namen stehen nicht in dieser Tabelle.
     *
     * @param list<string> $companyIds
     * @param list<string> $contactIds
     *
     * @return list<Deal>
     */
    public function search(string $query, array $companyIds = [], array $contactIds = [], int $limit = 100): array;

    /**
     * @return list<Deal>
     */
    public function findByStage(Stage $stage, int $limit = 100): array;

    /**
     * Anzahl und Summe je Stufe - in der Datenbank aggregiert.
     *
     * @return array<string, array{count: int, cents: int}> Schluessel ist der
     *                                                      Stage-Wert.
     */
    public function statsByStage(): array;

    public function countAll(): int;

    public function countByStage(Stage $stage): int;
}
