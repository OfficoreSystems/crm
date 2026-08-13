<?php

declare(strict_types=1);

namespace Crm\Deal\Infrastructure\SharedKernel;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\DealRepositoryInterface;
use Crm\SharedKernel\Localization\TranslatableText;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DealSubjectResolver implements SubjectResolverInterface
{
    public const TYPE = 'deal';

    public function __construct(
        private DealRepositoryInterface $deals,
    ) {
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function typeLabel(): string
    {
        return 'deal.subject_type';
    }

    public function resolve(array $ids): array
    {
        $resolved = [];

        foreach ($ids as $id) {
            if (!Uuid::isValid($id)) {
                continue;
            }

            $deal = $this->deals->find(Uuid::fromString($id));

            if (null === $deal) {
                continue;
            }

            $resolved[$id] = self::toSubject($deal);
        }

        return $resolved;
    }

    public function search(string $query, int $limit = 10): array
    {
        return array_map(self::toSubject(...), $this->deals->search($query, [], [], $limit));
    }

    private static function toSubject(Deal $deal): ResolvedSubject
    {
        return new ResolvedSubject(
            type: self::TYPE,
            id: (string) $deal->id(),
            label: $deal->title(),
            route: 'deal_index',
            typeLabel: 'deal.subject_type',
            // Der Wert ist Daten, die Stufe ist Uebersetzung - deshalb ein
            // TranslatableText mit verschachteltem Platzhalter statt einer
            // fertigen Zeichenkette.
            description: TranslatableText::of('deal.subject_description', [
                '%company%' => $deal->value()->asDecimal().' '.$deal->value()->currency,
                '%stage%' => TranslatableText::of($deal->stage()->label()),
            ]),
        );
    }
}
