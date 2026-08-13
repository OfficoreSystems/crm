<?php

declare(strict_types=1);

namespace Crm\Calendar\Tests\Domain;

use Crm\Calendar\Domain\CalendarFeed;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Das Token ist der einzige Ausweis, den ein Kalenderclient hat.
 *
 * Es steht in einer URL, die in Outlook-Profilen, Browser-Historien und
 * Serverlogs landet. Diese Tests halten fest, was daraus folgt.
 */
final class CalendarFeedTest extends TestCase
{
    #[Test]
    public function the_plain_token_is_handed_out_exactly_once(): void
    {
        [$feed, $token] = CalendarFeed::issueFor(Uuid::v7());

        self::assertNotSame('', $token);

        // Es gibt keinen Weg, es dem Feed noch einmal zu entlocken.
        self::assertFalse(
            method_exists($feed, 'token'),
            'Ein Getter fuers Klartext-Token wuerde den Hash zur Dekoration machen.',
        );
    }

    #[Test]
    public function two_tokens_are_never_the_same(): void
    {
        $tokens = [];

        for ($i = 0; $i < 200; ++$i) {
            [, $token] = CalendarFeed::issueFor(Uuid::v7());
            $tokens[] = $token;
        }

        self::assertCount(200, array_unique($tokens));
    }

    #[Test]
    public function the_token_survives_a_url_untouched(): void
    {
        // base64url statt base64: "+", "/" und "=" muessten in einer URL
        // kodiert werden, und spaetestens beim Kopieren aus einer E-Mail geht
        // dabei etwas schief.
        for ($i = 0; $i < 50; ++$i) {
            [, $token] = CalendarFeed::issueFor(Uuid::v7());

            self::assertSame($token, rawurlencode($token), 'Das Token muss ohne Kodierung in eine URL passen.');
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
        }
    }

    #[Test]
    public function the_token_is_long_enough_to_be_unguessable(): void
    {
        // 32 Byte, base64 kodiert: 43 Zeichen ohne Fuellzeichen.
        [, $token] = CalendarFeed::issueFor(Uuid::v7());

        self::assertSame(43, \strlen($token));
    }

    #[Test]
    public function hashing_is_deterministic_so_the_lookup_can_find_it(): void
    {
        // Ohne Salt - und das ist hier richtig: gesucht wird *nach* dem Wert,
        // und 32 zufaellige Bytes haben keine Rainbow Table.
        [, $token] = CalendarFeed::issueFor(Uuid::v7());

        self::assertSame(CalendarFeed::hash($token), CalendarFeed::hash($token));
        self::assertNotSame(CalendarFeed::hash($token), CalendarFeed::hash($token.'x'));
        self::assertSame(64, \strlen(CalendarFeed::hash($token)), 'SHA-256 als Hex.');
    }

    #[Test]
    public function regenerating_invalidates_the_old_address(): void
    {
        // Der Grund, warum es diese Methode gibt: eine URL, die einmal in
        // einer E-Mail gelandet ist, bekommt man nicht zurueck. Man kann sie
        // nur wertlos machen.
        [$feed, $old] = CalendarFeed::issueFor(Uuid::v7());

        $new = $feed->regenerate();

        self::assertNotSame($old, $new);
    }

    #[Test]
    public function regenerating_forgets_when_it_was_last_used(): void
    {
        // Sonst saehe es so aus, als sei die neue Adresse bereits im Einsatz -
        // und niemand merkt, dass das Abonnement nie umgestellt wurde.
        [$feed] = CalendarFeed::issueFor(Uuid::v7());
        $feed->markUsed();
        self::assertNotNull($feed->lastUsedAt());

        $feed->regenerate();

        self::assertNull($feed->lastUsedAt());
    }

    #[Test]
    public function a_fresh_feed_has_never_been_used(): void
    {
        [$feed] = CalendarFeed::issueFor(Uuid::v7());

        self::assertNull($feed->lastUsedAt());
    }

    #[Test]
    public function it_remembers_who_it_belongs_to(): void
    {
        $userId = Uuid::v7();

        [$feed] = CalendarFeed::issueFor($userId);

        self::assertTrue($userId->equals($feed->userId()));
    }
}
