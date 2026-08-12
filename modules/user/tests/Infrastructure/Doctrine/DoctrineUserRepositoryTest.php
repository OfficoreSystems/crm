<?php

declare(strict_types=1);

namespace Crm\User\Tests\Infrastructure\Doctrine;

use Crm\User\Domain\Team;
use Crm\User\Domain\TeamRepositoryInterface;
use Crm\User\Domain\User;
use Crm\User\Domain\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepositoryInterface $users;
    private TeamRepositoryInterface $teams;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->users = $container->get(UserRepositoryInterface::class);
        $this->teams = $container->get(TeamRepositoryInterface::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();

        parent::tearDown();
    }

    #[Test]
    public function it_persists_and_finds_a_user(): void
    {
        $user = User::create('anna@example.test', 'Anna Berger', 'hash');
        $this->users->save($user);

        $this->entityManager->clear();
        $found = $this->users->find($user->id());

        self::assertNotNull($found);
        self::assertSame('anna@example.test', $found->email());
        self::assertSame('Anna Berger', $found->name());
    }

    #[Test]
    public function roles_survive_a_round_trip(): void
    {
        // Rollen liegen als JSON in der Spalte. Der Test faengt ab, dass beim
        // Serialisieren aus der Liste ein Objekt wird.
        $user = User::create('anna@example.test', 'Anna', 'hash', [\Crm\User\Domain\Role::ADMIN]);
        $this->users->save($user);

        $this->entityManager->clear();

        self::assertSame($user->roles(), $this->users->find($user->id())?->roles());
    }

    #[Test]
    public function it_finds_a_user_by_email_regardless_of_case_and_padding(): void
    {
        $this->users->save(User::create('anna@example.test', 'Anna', 'hash'));

        self::assertNotNull($this->users->findByEmail('anna@example.test'));
        self::assertNotNull($this->users->findByEmail('  ANNA@Example.TEST  '));
        self::assertTrue($this->users->emailExists('Anna@Example.test'));
        self::assertFalse($this->users->emailExists('niemand@example.test'));
    }

    #[Test]
    public function it_persists_the_team_association(): void
    {
        $team = Team::create('Vertrieb');
        $this->teams->save($team);

        $user = User::create('anna@example.test', 'Anna', 'hash');
        $user->joinTeam($team);
        $this->users->save($user);

        $this->entityManager->clear();
        $found = $this->users->find($user->id());

        self::assertSame('Vertrieb', $found?->team()?->name());
    }

    #[Test]
    public function it_removes_a_user(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash');
        $this->users->save($user);

        $this->users->remove($user);

        self::assertNull($this->users->find($user->id()));
        self::assertSame(0, $this->users->countAll());
    }

    #[Test]
    public function it_searches_name_and_email(): void
    {
        $this->givenUsers();

        self::assertCount(1, $this->users->search('Berger'));
        self::assertCount(1, $this->users->search('bogdan@'));
        self::assertCount(3, $this->users->search(''));
    }

    #[Test]
    public function the_search_is_case_insensitive_and_escapes_wildcards(): void
    {
        $this->givenUsers();

        self::assertCount(1, $this->users->search('bERgEr'));
        self::assertCount(0, $this->users->search('%'));
        self::assertCount(0, $this->users->search('_erger'));
    }

    #[Test]
    public function the_search_is_sorted_by_name_and_respects_the_limit(): void
    {
        $this->givenUsers();

        $names = array_map(static fn (User $u): string => $u->name(), $this->users->search(''));
        self::assertSame(['Anna Berger', 'Bogdan Petrov', 'Clara Dupont'], $names);

        self::assertCount(2, $this->users->search('', 2));
        self::assertCount(1, $this->users->search('', 0), 'max(1, limit) verhindert eine leere Liste');
    }

    #[Test]
    public function it_lists_only_active_users(): void
    {
        $this->givenUsers();
        $bogdan = $this->users->findByEmail('bogdan@example.test');
        self::assertNotNull($bogdan);
        $bogdan->deactivate();
        $this->users->save($bogdan);

        $active = $this->users->findAllActive();

        self::assertCount(2, $active);
        self::assertSame(3, $this->users->countAll(), 'countAll zaehlt auch deaktivierte');
    }

    private function givenUsers(): void
    {
        $this->users->save(User::create('anna@example.test', 'Anna Berger', 'hash'));
        $this->users->save(User::create('bogdan@example.test', 'Bogdan Petrov', 'hash'));
        $this->users->save(User::create('clara@example.test', 'Clara Dupont', 'hash'));
    }
}
