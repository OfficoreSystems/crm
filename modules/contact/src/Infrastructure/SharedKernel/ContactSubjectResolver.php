<?php

declare(strict_types=1);

namespace Crm\Contact\Infrastructure\SharedKernel;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Domain\ContactRepositoryInterface;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Macht Kontakte als Subjekt verweisbar.
 *
 * Damit kann eine Aktivitaet an einem Kontakt haengen, ohne dass das
 * activity-Modul dieses hier kennt.
 */
final readonly class ContactSubjectResolver implements SubjectResolverInterface
{
    public const TYPE = 'contact';

    public function __construct(
        private ContactRepositoryInterface $contacts,
    ) {
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function typeLabel(): string
    {
        return 'Kontakt';
    }

    public function resolve(array $ids): array
    {
        $resolved = [];

        foreach ($ids as $id) {
            if (!Uuid::isValid($id)) {
                continue;
            }

            $contact = $this->contacts->find(Uuid::fromString($id));

            if (null === $contact) {
                continue;
            }

            $resolved[$id] = self::toSubject($contact);
        }

        return $resolved;
    }

    public function search(string $query, int $limit = 10): array
    {
        return array_map(self::toSubject(...), $this->contacts->search($query, [], $limit));
    }

    private static function toSubject(Contact $contact): ResolvedSubject
    {
        return new ResolvedSubject(
            type: self::TYPE,
            id: (string) $contact->id(),
            label: $contact->fullName(),
            route: 'contact_index',
            typeLabel: 'Kontakt',
            // Bewusst nur die E-Mail und nicht der Firmenname: den
            // aufzuloesen hiesse, hier den CompanyFinder zu bemuehen - je
            // Treffer, in einer Schleife. Fuer eine Trefferliste ist das der
            // falsche Ort.
            description: $contact->email(),
        );
    }
}
