<?php

declare(strict_types=1);

namespace Crm\User\Tests\Domain;

use Crm\User\Domain\Team;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class TeamTest extends TestCase
{
    #[Test]
    public function it_trims_the_name(): void
    {
        self::assertSame('Vertrieb', Team::create('  Vertrieb  ')->name());
    }

    #[Test]
    public function it_rejects_a_blank_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Team::create('   ');
    }

    #[Test]
    public function it_can_be_renamed(): void
    {
        $team = Team::create('Vertrieb');
        $team->rename(' Innendienst ');

        self::assertSame('Innendienst', $team->name());
    }

    #[Test]
    public function renaming_rejects_a_blank_name(): void
    {
        $team = Team::create('Vertrieb');

        $this->expectException(\InvalidArgumentException::class);

        $team->rename('');
    }

    #[Test]
    public function it_assigns_a_sortable_uuid_and_keeps_the_timestamp(): void
    {
        $moment = new \DateTimeImmutable('2026-03-01 09:15:00');

        $team = Team::create('Vertrieb', $moment);

        self::assertInstanceOf(UuidV7::class, $team->id());
        self::assertSame($moment, $team->createdAt());
    }
}
