<?php

declare(strict_types=1);

namespace Crm\User\Tests\Infrastructure\SharedKernel;

use Crm\User\Domain\Team;
use Crm\User\Domain\User;
use Crm\User\Infrastructure\SharedKernel\DoctrineUserFinder;
use Crm\User\Tests\Double\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Der Finder ist das, was andere Module vom user-Modul sehen. Er darf nie
 * Entities nach draussen geben - nur UserSummary.
 */
final class DoctrineUserFinderTest extends TestCase
{
    private InMemoryUserRepository $users;
    private DoctrineUserFinder $finder;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->finder = new DoctrineUserFinder($this->users);
    }

    #[Test]
    public function it_maps_a_user_to_a_summary(): void
    {
        $team = Team::create('Vertrieb');
        $user = User::create('anna@example.test', 'Anna Berger', 'hash');
        $user->joinTeam($team);
        $this->users->save($user);

        $summary = $this->finder->find((string) $user->id());

        self::assertNotNull($summary);
        self::assertSame((string) $user->id(), $summary->id);
        self::assertSame('Anna Berger', $summary->name);
        self::assertSame('anna@example.test', $summary->email);
        self::assertSame((string) $team->id(), $summary->teamId);
        self::assertTrue($summary->active);
    }

    #[Test]
    public function a_user_without_a_team_has_a_null_team_id(): void
    {
        $user = User::create('anna@example.test', 'Anna', 'hash');
        $this->users->save($user);

        self::assertNull($this->finder->find((string) $user->id())?->teamId);
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        self::assertNull($this->finder->find((string) Uuid::v7()));
    }

    #[Test]
    public function a_malformed_id_returns_null_instead_of_throwing(): void
    {
        // Aufrufer sind andere Module. Eine veraltete oder falsch getippte ID
        // darf dort keine Ausnahme ausloesen.
        self::assertNull($this->finder->find('keine-uuid'));
        self::assertNull($this->finder->find(''));
    }

    #[Test]
    public function find_many_skips_unknown_ids_instead_of_returning_null_entries(): void
    {
        $anna = User::create('anna@example.test', 'Anna', 'hash');
        $bogdan = User::create('bogdan@example.test', 'Bogdan', 'hash');
        $this->users->save($anna);
        $this->users->save($bogdan);

        $found = $this->finder->findMany([
            (string) $anna->id(),
            (string) Uuid::v7(),
            (string) $bogdan->id(),
            'kaputt',
        ]);

        self::assertCount(2, $found);
        self::assertArrayHasKey((string) $anna->id(), $found);
        self::assertArrayHasKey((string) $bogdan->id(), $found);
    }

    #[Test]
    public function find_many_indexes_by_id(): void
    {
        $anna = User::create('anna@example.test', 'Anna', 'hash');
        $this->users->save($anna);

        $found = $this->finder->findMany([(string) $anna->id()]);

        self::assertSame('Anna', $found[(string) $anna->id()]->name);
    }

    #[Test]
    public function it_lists_only_active_users(): void
    {
        $anna = User::create('anna@example.test', 'Anna', 'hash');
        $bogdan = User::create('bogdan@example.test', 'Bogdan', 'hash');
        $bogdan->deactivate();
        $this->users->save($anna);
        $this->users->save($bogdan);

        $active = $this->finder->findAllActive();

        self::assertCount(1, $active);
        self::assertSame('Anna', $active[0]->name);
    }
}
