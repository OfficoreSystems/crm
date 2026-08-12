<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\User;

use Crm\SharedKernel\User\NullUserFinder;
use Crm\SharedKernel\User\UserSummary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Der Vorgabewert, solange kein user-Modul installiert ist. Er muss leere
 * Antworten liefern statt zu werfen - sonst waere jedes Modul, das
 * UserFinderInterface injiziert, ohne user-Modul kaputt.
 */
final class NullUserFinderTest extends TestCase
{
    #[Test]
    public function it_finds_nobody(): void
    {
        $finder = new NullUserFinder();

        self::assertNull($finder->find('egal'));
        self::assertSame([], $finder->findMany(['a', 'b']));
        self::assertSame([], $finder->findAllActive());
    }

    #[Test]
    public function a_summary_carries_the_fields_other_modules_need(): void
    {
        $summary = new UserSummary('id-1', 'Anna Berger', 'anna@example.test', 'team-1');

        self::assertSame('id-1', $summary->id);
        self::assertSame('Anna Berger', $summary->name);
        self::assertSame('anna@example.test', $summary->email);
        self::assertSame('team-1', $summary->teamId);
        self::assertTrue($summary->active);
    }

    #[Test]
    public function a_summary_may_have_no_team(): void
    {
        self::assertNull((new UserSummary('id-1', 'Anna', 'anna@example.test'))->teamId);
    }
}
