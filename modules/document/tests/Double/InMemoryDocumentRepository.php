<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Double;

use Crm\Document\Domain\Document;
use Crm\Document\Domain\DocumentRepositoryInterface;
use Crm\SharedKernel\Subject\SubjectRef;
use Symfony\Component\Uid\Uuid;

final class InMemoryDocumentRepository implements DocumentRepositoryInterface
{
    /**
     * @var array<string, Document>
     */
    private array $documents = [];

    /**
     * Zaehlt mit, wie oft gespeichert wurde - der Upload-Test braucht das, um
     * das Aufraeumen nach einem Fehlschlag zu pruefen.
     */
    public int $saveCalls = 0;

    public ?\Throwable $failOnSave = null;

    public function save(Document $document): void
    {
        ++$this->saveCalls;

        if (null !== $this->failOnSave) {
            throw $this->failOnSave;
        }

        $this->documents[$document->id()->toString()] = $document;
    }

    public function remove(Document $document): void
    {
        unset($this->documents[$document->id()->toString()]);
    }

    public function find(Uuid $id): ?Document
    {
        return $this->documents[$id->toString()] ?? null;
    }

    public function findForSubject(SubjectRef $subject, int $limit = 100): array
    {
        $found = array_filter(
            $this->documents,
            static fn (Document $d): bool => $d->subject()->key() === $subject->key(),
        );

        return \array_slice(array_values($found), 0, $limit);
    }

    public function findRecent(int $limit = 50): array
    {
        return \array_slice(array_values($this->documents), 0, $limit);
    }

    public function countForSubject(SubjectRef $subject): int
    {
        return \count($this->findForSubject($subject, \PHP_INT_MAX));
    }

    public function countAll(): int
    {
        return \count($this->documents);
    }

    public function totalBytes(): int
    {
        return array_sum(array_map(static fn (Document $d): int => $d->size(), $this->documents));
    }
}
