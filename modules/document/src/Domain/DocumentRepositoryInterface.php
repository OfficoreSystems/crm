<?php

declare(strict_types=1);

namespace Crm\Document\Domain;

use Crm\SharedKernel\Subject\SubjectRef;
use Symfony\Component\Uid\Uuid;

interface DocumentRepositoryInterface
{
    public function save(Document $document): void;

    public function remove(Document $document): void;

    public function find(Uuid $id): ?Document;

    /**
     * @return list<Document>
     */
    public function findForSubject(SubjectRef $subject, int $limit = 100): array;

    /**
     * @return list<Document>
     */
    public function findRecent(int $limit = 50): array;

    public function countForSubject(SubjectRef $subject): int;

    public function countAll(): int;

    /**
     * Summe aller Dateigroessen in Bytes - fuer die Kennzahl auf der
     * Uebersicht. Wird in der Datenbank gerechnet, nicht in PHP.
     */
    public function totalBytes(): int;
}
