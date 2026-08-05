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

    public function search(string $query, int $limit = 50): array
    {
        $needle = mb_strtolower(trim($query));

        $matches = array_values(array_filter(
            $this->contacts,
            static function (Contact $contact) use ($needle): bool {
                if ('' === $needle) {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', array_filter([
                    $contact->firstName(),
                    $contact->lastName(),
                    $contact->email(),
                    $contact->company(),
                ])));

                return str_contains($haystack, $needle);
            },
        ));

        usort(
            $matches,
            static fn (Contact $a, Contact $b): int => [$a->lastName(), $a->firstName()] <=> [$b->lastName(), $b->firstName()],
        );

        return \array_slice($matches, 0, max(1, $limit));
    }

    public function countAll(): int
    {
        return \count($this->contacts);
    }
}
