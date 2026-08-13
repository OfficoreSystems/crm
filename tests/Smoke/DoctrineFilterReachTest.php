<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Domain\Money;
use Crm\Deal\Domain\Stage;
use Crm\SharedKernel\Security\AccessScope;
use Crm\SharedKernel\Security\OwnershipRegistry;
use Crm\SharedKernel\Infrastructure\Security\RecordVisibilityFilter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Wie weit der Doctrine-Filter reicht.
 *
 * Die Frage ist nicht akademisch: greift der Filter nur bei Listenabfragen,
 * dann kommt jeder an einen fremden Datensatz, der dessen ID kennt - die Liste
 * waere sauber und die Detailseite offen.
 *
 * Der zweite Test haelt ausserdem die Falle fest, die beim Bau dieser Tests
 * eine Stunde gekostet hat: die Identity Map beantwortet find() ohne SQL, und
 * ohne SQL gibt es keinen Filter. In der Anwendung ist das harmlos - jeder
 * Request beginnt mit einem frischen EntityManager. In Tests nicht.
 */
final class DoctrineFilterReachTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('DELETE FROM deal_deals');
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->executeStatement('DELETE FROM deal_deals');

        parent::tearDown();
    }

    #[Test]
    public function neither_a_query_nor_a_lookup_by_id_returns_the_foreign_record(): void
    {
        $id = $this->givenDealOfAnotherTeam();
        $this->enableFilterForAnOutsider();
        $this->entityManager->clear();

        self::assertSame([], $this->entityManager->getRepository(Deal::class)->findBy(['id' => $id]));
        self::assertNull(
            $this->entityManager->find(Deal::class, $id),
            'Wer die ID kennt, darf trotzdem nicht an den Datensatz kommen.',
        );
    }

    #[Test]
    public function an_already_loaded_entity_bypasses_the_filter(): void
    {
        // Kein Loch in der Anwendung, sondern die Arbeitsweise von Doctrine:
        // was bereits im Speicher liegt, wird nicht noch einmal geladen. Diese
        // Zusicherung steht hier, damit der naechste Testautor nicht wieder
        // darueber stolpert - und damit auffaellt, falls sich das aendert.
        $id = $this->givenDealOfAnotherTeam();
        $this->enableFilterForAnOutsider();

        self::assertInstanceOf(Deal::class, $this->entityManager->find(Deal::class, $id));
    }

    private function givenDealOfAnotherTeam(): Uuid
    {
        $deal = Deal::create(
            title: 'Rahmenvertrag Seefracht',
            value: Money::fromDecimal('84000.00'),
            stage: Stage::NEGOTIATION,
            ownerId: Uuid::v7(),
            ownerTeamId: Uuid::v7(),
        );

        $this->entityManager->persist($deal);
        $this->entityManager->flush();

        return $deal->id();
    }

    private function enableFilterForAnOutsider(): void
    {
        $filter = $this->entityManager->getFilters()->enable(RecordVisibilityFilter::NAME);
        \assert($filter instanceof RecordVisibilityFilter);

        // Die echte Registry, nicht eine handgeschriebene Liste: so prueft
        // dieser Test auch, dass das deal-Modul seine Spalten ueberhaupt
        // meldet. Eine feste Liste hier wuerde gruen bleiben, wenn das Modul
        // sie eines Tages vergisst.
        $filter->useRestrictions(static::getContainer()->get(OwnershipRegistry::class)->restrictions());

        $filter->setParameter('actor_id', (string) Uuid::v7());
        $filter->setParameter('actor_team_id', (string) Uuid::v7());
        $filter->setParameter('scope_deal', AccessScope::TEAM->value);
    }
}
