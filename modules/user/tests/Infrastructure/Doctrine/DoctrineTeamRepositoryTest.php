<?php

declare(strict_types=1);

namespace Crm\User\Tests\Infrastructure\Doctrine;

use Crm\User\Domain\Team;
use Crm\User\Domain\TeamRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineTeamRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private TeamRepositoryInterface $teams;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->teams = $container->get(TeamRepositoryInterface::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();

        parent::tearDown();
    }

    #[Test]
    public function it_persists_and_finds_a_team(): void
    {
        $team = Team::create('Vertrieb');
        $this->teams->save($team);

        $this->entityManager->clear();

        self::assertSame('Vertrieb', $this->teams->find($team->id())?->name());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        self::assertNull($this->teams->find(Uuid::v7()));
    }

    #[Test]
    public function it_finds_a_team_by_name(): void
    {
        $this->teams->save(Team::create('Vertrieb'));

        self::assertNotNull($this->teams->findByName('Vertrieb'));
        self::assertNotNull($this->teams->findByName('  Vertrieb  '));
        self::assertNull($this->teams->findByName('Marketing'));
    }

    #[Test]
    public function it_lists_teams_sorted_by_name(): void
    {
        $this->teams->save(Team::create('Vertrieb'));
        $this->teams->save(Team::create('Innendienst'));
        $this->teams->save(Team::create('Marketing'));

        $names = array_map(static fn (Team $t): string => $t->name(), $this->teams->findAll());

        self::assertSame(['Innendienst', 'Marketing', 'Vertrieb'], $names);
        self::assertSame(3, $this->teams->countAll());
    }

    #[Test]
    public function it_removes_a_team(): void
    {
        $team = Team::create('Vertrieb');
        $this->teams->save($team);

        $this->teams->remove($team);

        self::assertSame(0, $this->teams->countAll());
    }
}
