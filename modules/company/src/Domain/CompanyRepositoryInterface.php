<?php

declare(strict_types=1);

namespace Crm\Company\Domain;

use Symfony\Component\Uid\Uuid;

interface CompanyRepositoryInterface
{
    public function save(Company $company): void;

    public function remove(Company $company): void;

    public function find(Uuid $id): ?Company;

    public function findByName(string $name): ?Company;

    /**
     * Freitextsuche ueber Name, Branche und Ort.
     *
     * @return list<Company>
     */
    public function search(string $query, int $limit = 50): array;

    /**
     * @return list<Company>
     */
    public function findAll(): array;

    /**
     * Branchen mit Anzahl der Firmen, absteigend.
     *
     * Aggregiert bewusst in der Datenbank: die Alternative waere, alle Firmen
     * zu laden und in PHP zu zaehlen.
     *
     * @return array<string, int>
     */
    public function countByIndustry(): array;

    public function countAll(): int;
}
