<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\Infrastructure;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\DealRepositoryInterface;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineDealRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DealRepositoryInterface $deals;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->deals = $container->get(DealRepositoryInterface::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();

        parent::tearDown();
    }

    #[Test]
    public function money_survives_a_round_trip_without_rounding(): void
    {
        // Der eigentliche Grund fuer BIGINT statt DECIMAL oder FLOAT: der Wert
        // muss auf den Cent genau zurueckkommen.
        $deal = Deal::create('Rahmenvertrag', Money::fromDecimal('84000.99'));
        $this->deals->save($deal);

        $this->entityManager->clear();
        $found = $this->deals->find($deal->id());

        self::assertSame(8400099, $found?->value()->amount);
        self::assertSame('84000.99', $found?->value()->asDecimal());
        self::assertSame('EUR', $found?->value()->currency);
    }

    #[Test]
    public function a_foreign_currency_survives_a_round_trip(): void
    {
        $deal = Deal::create('Ausschreibung Basel', Money::fromDecimal('50000.00', 'CHF'));
        $this->deals->save($deal);

        $this->entityManager->clear();

        self::assertSame('CHF', $this->deals->find($deal->id())?->value()->currency);
    }

    #[Test]
    public function the_stage_enum_survives_a_round_trip(): void
    {
        $deal = Deal::create('Rahmenvertrag', stage: Stage::NEGOTIATION);
        $this->deals->save($deal);

        $this->entityManager->clear();

        self::assertSame(Stage::NEGOTIATION, $this->deals->find($deal->id())?->stage());
    }

    #[Test]
    public function it_removes_a_deal(): void
    {
        $deal = Deal::create('Rahmenvertrag');
        $this->deals->save($deal);

        $this->deals->remove($deal);

        self::assertSame(0, $this->deals->countAll());
        self::assertNull($this->deals->find($deal->id()));
    }

    #[Test]
    public function it_filters_by_stage(): void
    {
        $this->givenDeals();

        self::assertCount(2, $this->deals->findByStage(Stage::LEAD));
        self::assertSame(2, $this->deals->countByStage(Stage::LEAD));
        self::assertSame(1, $this->deals->countByStage(Stage::WON));
        self::assertSame(0, $this->deals->countByStage(Stage::PROPOSAL));
    }

    #[Test]
    public function it_aggregates_count_and_sum_per_stage_in_the_database(): void
    {
        $this->givenDeals();

        $stats = $this->deals->statsByStage();

        self::assertSame(['count' => 2, 'cents' => 30000], $stats[Stage::LEAD->value]);
        self::assertSame(['count' => 1, 'cents' => 50000], $stats[Stage::WON->value]);
        self::assertArrayNotHasKey(Stage::PROPOSAL->value, $stats, 'Leere Stufen tauchen nicht auf');
    }

    #[Test]
    public function the_search_matches_the_title(): void
    {
        $this->givenDeals();

        self::assertCount(1, $this->deals->search('Seefracht'));
        self::assertCount(1, $this->deals->search('seefracht'), 'case-insensitiv');
        self::assertCount(0, $this->deals->search('%'), 'Wildcards werden escaped');
    }

    #[Test]
    public function the_search_widens_to_companies_and_contacts(): void
    {
        // Firmen- und Kontaktnamen stehen nicht in dieser Tabelle. Der
        // Aufrufer hat sie vorher ueber die Finder zu IDs aufgeloest.
        $company = Uuid::v7();
        $contact = Uuid::v7();
        $this->deals->save(Deal::create('Ohne Bezug im Titel', companyId: $company));
        $this->deals->save(Deal::create('Auch ohne', contactId: $contact));

        self::assertCount(0, $this->deals->search('Nordwind'));
        self::assertCount(1, $this->deals->search('Nordwind', [(string) $company]));
        self::assertCount(1, $this->deals->search('Anna', [], [(string) $contact]));
        self::assertCount(2, $this->deals->search('egal', [(string) $company], [(string) $contact]));
    }

    #[Test]
    public function results_are_sorted_by_value_descending(): void
    {
        $this->givenDeals();

        $titles = array_map(static fn (Deal $d): string => $d->title(), $this->deals->search(''));

        self::assertSame('Wartungsvertrag', $titles[0], 'Der teuerste zuerst');
    }

    #[Test]
    public function it_respects_the_limit(): void
    {
        $this->givenDeals();

        self::assertCount(2, $this->deals->search('', [], [], 2));
        self::assertCount(1, $this->deals->search('', [], [], 0), 'max(1, limit)');
    }

    private function givenDeals(): void
    {
        $this->deals->save(Deal::create('Rahmenvertrag Seefracht', Money::fromCents(20000), Stage::LEAD));
        $this->deals->save(Deal::create('Neubau Buerokomplex', Money::fromCents(10000), Stage::LEAD));
        $this->deals->save(Deal::create('Wartungsvertrag', Money::fromCents(50000), Stage::WON));
    }
}
