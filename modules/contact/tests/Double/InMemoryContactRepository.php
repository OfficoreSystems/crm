<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Double;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Test-Double fuer den Port. Bildet die Suche naeherungsweise nach - genug,
 * um Use-Cases ohne Datenbank zu testen.
 */
final class InMemoryContactRepository implements ContactRepositoryInterface
{
    /**
     * @var array<string, Contact>
     */
    private array $contacts = [];

    public function save(Contact $contact): void
    {
        $this->contacts[(string) $contact->id()] = $contact;
    }

    public function remove(Contact $contact): void
    {
        unset($this->contacts[(string) $contact->id()]);
    }

    public function find(Uuid $id): ?Contact
    {
        return $this->contacts[(string) $id] ?? null;
    }

    public function search(string $query, array $companyIds = [], int $limit = 50): array
    {
        $needle = mb_strtolower(trim($query));

        $matches = array_values(array_filter(
            $this->contacts,
            static function (Contact $contact) use ($needle, $companyIds): bool {
                if ('' === $needle) {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', array_filter([
                    $contact->firstName(),
                    $contact->lastName(),
                    $contact->email(),
                ])));

                if (str_contains($haystack, $needle)) {
                    return true;
                }

                // ODER-verknuepft wie im Doctrine-Repository.
                return \in_array($contact->companyId()?->toString(), $companyIds, true);
            },
        ));

        return \array_slice(self::sorted($matches), 0, max(1, $limit));
    }

    public function findByCompanyIds(array $companyIds, int $limit = 50): array
    {
        if ([] === $companyIds) {
            return [];
        }

        $matches = array_values(array_filter(
            $this->contacts,
            static fn (Contact $contact): bool => \in_array($contact->companyId()?->toString(), $companyIds, true),
        ));

        return \array_slice(self::sorted($matches), 0, max(1, $limit));
    }

    public function countByCompanyId(string $companyId): int
    {
        return \count(array_filter(
            $this->contacts,
            static fn (Contact $contact): bool => $contact->companyId()?->toString() === $companyId,
        ));
    }

    public function countAll(): int
    {
        return \count($this->contacts);
    }

    /**
     * @param list<Contact> $contacts
     *
     * @return list<Contact>
     */
    private static function sorted(array $contacts): array
    {
        usort(
            $contacts,
            static fn (Contact $a, Contact $b): int => [$a->lastName(), $a->firstName()] <=> [$b->lastName(), $b->firstName()],
        );

        return $contacts;
    }
}
