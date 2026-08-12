<?php

declare(strict_types=1);

namespace Crm\Contact\Infrastructure\SharedKernel;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Crm\SharedKernel\Contact\ContactFinderInterface;
use Crm\SharedKernel\Contact\ContactSummary;
use Symfony\Component\Uid\Uuid;

/**
 * Bedient den Extension-Point des Shared Kernel.
 *
 * Gibt nie eine Entity nach draussen - nur {@see ContactSummary}.
 */
final readonly class DoctrineContactFinder implements ContactFinderInterface
{
    public function __construct(
        private ContactRepositoryInterface $contacts,
    ) {
    }

    public function find(string $id): ?ContactSummary
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $contact = $this->contacts->find(Uuid::fromString($id));

        return null === $contact ? null : self::toSummary($contact);
    }

    public function findMany(array $ids): array
    {
        $summaries = [];

        foreach ($ids as $id) {
            $summary = $this->find($id);

            if (null !== $summary) {
                $summaries[$id] = $summary;
            }
        }

        return $summaries;
    }

    public function searchByName(string $query, int $limit = 25): array
    {
        if ('' === trim($query)) {
            return [];
        }

        return array_map(self::toSummary(...), $this->contacts->search($query, [], $limit));
    }

    public function exists(string $id): bool
    {
        return null !== $this->find($id);
    }

    private static function toSummary(Contact $contact): ContactSummary
    {
        return new ContactSummary(
            id: (string) $contact->id(),
            fullName: $contact->fullName(),
            email: $contact->email(),
            companyId: $contact->companyId()?->toString(),
        );
    }
}
