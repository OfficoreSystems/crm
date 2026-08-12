<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\UI;

use Crm\SharedKernel\Contact\ContactFinderInterface;
use Crm\SharedKernel\Contact\ContactSummary;

final class FakeContacts implements ContactFinderInterface
{
    public int $findManyCalls = 0;

    /**
     * @var array<string, ContactSummary>
     */
    private array $contacts = [];

    /**
     * @param list<ContactSummary> $contacts
     */
    public function __construct(array $contacts)
    {
        foreach ($contacts as $contact) {
            $this->contacts[$contact->id] = $contact;
        }
    }

    public function find(string $id): ?ContactSummary
    {
        return $this->contacts[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        ++$this->findManyCalls;

        $found = [];

        foreach ($ids as $id) {
            if (isset($this->contacts[$id])) {
                $found[$id] = $this->contacts[$id];
            }
        }

        return $found;
    }

    public function searchByName(string $query, int $limit = 25): array
    {
        $needle = mb_strtolower(trim($query));

        if ('' === $needle) {
            return [];
        }

        return array_values(array_filter(
            $this->contacts,
            static fn (ContactSummary $c): bool => str_contains(mb_strtolower($c->fullName), $needle),
        ));
    }

    public function exists(string $id): bool
    {
        return isset($this->contacts[$id]);
    }
}
