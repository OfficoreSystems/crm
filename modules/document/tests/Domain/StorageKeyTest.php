<?php

declare(strict_types=1);

namespace Crm\Document\Tests\Domain;

use Crm\Document\Domain\StorageKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StorageKeyTest extends TestCase
{
    #[Test]
    public function two_keys_are_never_the_same(): void
    {
        // Der eigentliche Zweck. Waere der Schluessel aus dem Dateinamen
        // abgeleitet, wuerde der zweite "Angebot.pdf" den ersten
        // ueberschreiben - und niemand merkte es.
        $keys = [];

        for ($i = 0; $i < 200; ++$i) {
            $keys[] = StorageKey::for('contact');
        }

        self::assertCount(200, array_unique($keys));
    }

    #[Test]
    public function it_sorts_by_type_and_month(): void
    {
        $key = StorageKey::for('contact', new \DateTimeImmutable('2026-08-13 10:00:00'));

        self::assertStringStartsWith('contact/2026/08/', $key);
    }

    #[Test]
    public function a_hostile_type_cannot_escape_the_prefix(): void
    {
        // Der Typ kommt aus der URL. Ohne Filterung liesse sich damit aus dem
        // Praefix ausbrechen und in einen fremden Bereich des Buckets
        // schreiben.
        $key = StorageKey::for('../../geheim');

        self::assertStringStartsNotWith('.', $key);
        self::assertStringNotContainsString('..', $key);
        self::assertStringStartsWith('geheim/', $key);
    }

    #[Test]
    public function a_type_without_any_usable_character_still_gets_a_home(): void
    {
        self::assertStringStartsWith('sonstiges/', StorageKey::for('///'));
    }
}
