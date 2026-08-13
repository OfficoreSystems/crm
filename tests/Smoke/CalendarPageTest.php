<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Crm\Calendar\Domain\AppointmentRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Der Weg vom Formular in die Datenbank.
 *
 * Interessant ist hier vor allem eine Stelle: ein datetime-local-Feld liefert
 * Ortszeit *ohne* Zeitzone. Welche gemeint ist, weiss der Browser - aber er
 * sagt es nicht. Die Annahme steht im Controller, und dieser Test haelt ihre
 * Wirkung fest.
 */
final class CalendarPageTest extends WebTestCase
{
    use SignsIn;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->purge(['calendar_appointments', 'calendar_feeds']);
        $this->signIn($this->client, $this->givenUser('vera@example.test', 'Vera', teamName: 'Vertrieb'));
    }

    protected function tearDown(): void
    {
        $this->purge(['calendar_appointments', 'calendar_feeds']);

        parent::tearDown();
    }

    #[Test]
    public function the_page_renders_and_registers_itself_in_the_navigation(): void
    {
        $crawler = $this->client->request('GET', '/kalender');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Kalender');
        self::assertSelectorTextContains('.sidebar__nav', 'Kalender');
        self::assertCount(1, $crawler->filter('.sidebar__nav a[aria-current="page"]'));
    }

    #[Test]
    public function a_summer_afternoon_in_berlin_becomes_utc_noon(): void
    {
        // Der eigentliche Punkt dieser Datei. Wer hier eine Stunde daneben
        // liegt, merkt es erst, wenn ein Kunde vor verschlossener Tuer steht.
        $this->submit(['beginn' => '2026-08-20T14:00', 'ende' => '2026-08-20T15:30']);

        $appointment = $this->appointments()->findRecent();

        self::assertSame('2026-08-20 12:00:00', $appointment->startsAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-20 13:30:00', $appointment->endsAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function the_same_wall_clock_time_in_winter_lands_an_hour_later(): void
    {
        // Eine feste Verschiebung um zwei Stunden waere im Winter falsch.
        $this->submit(['beginn' => '2026-01-20T14:00', 'ende' => '2026-01-20T15:00']);

        self::assertSame('13:00', $this->appointments()->findRecent()->startsAt()->format('H:i'));
    }

    #[Test]
    public function an_all_day_appointment_needs_no_end(): void
    {
        $this->submit(['beginn' => '2026-08-20T00:00', 'ganztaegig' => '1'], withEnd: false);

        $appointment = $this->appointments()->findRecent();

        self::assertTrue($appointment->isAllDay());
        self::assertSame('2026-08-21', $appointment->endsAt()->format('Y-m-d'));
    }

    #[Test]
    public function an_end_before_the_start_is_refused_with_a_message(): void
    {
        $this->submit(['beginn' => '2026-08-20T15:00', 'ende' => '2026-08-20T14:00']);

        self::assertSame(0, $this->countAll());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--error', 'enden, bevor er beginnt');
    }

    #[Test]
    public function a_missing_start_is_refused_with_a_message(): void
    {
        $this->submit(['beginn' => '', 'ende' => '2026-08-20T14:00']);

        self::assertSame(0, $this->countAll());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--error', 'Ohne Beginn');
    }

    #[Test]
    public function nonsense_in_the_date_field_does_not_break_the_page(): void
    {
        // Kommt nicht aus dem Formular, aber aus jeder selbstgebauten Anfrage.
        $this->submit(['beginn' => 'morgen vielleicht', 'ende' => '2026-08-20T14:00']);

        self::assertSame(0, $this->countAll());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--error', 'kein Zeitpunkt');
    }

    #[Test]
    public function an_unresolvable_subject_is_refused(): void
    {
        $this->submit([
            'beginn' => '2026-08-20T14:00',
            'ende' => '2026-08-20T15:00',
            'bezug_typ' => 'rechnung',
            'bezug_id' => 'r-1',
        ]);

        self::assertSame(0, $this->countAll());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--error', 'Kein Modul loest den Typ');
    }

    #[Test]
    public function an_appointment_without_a_subject_is_fine(): void
    {
        // Ein Teammeeting gehoert zu keinem Datensatz.
        $this->submit([
            'beginn' => '2026-08-20T14:00',
            'ende' => '2026-08-20T15:00',
            'bezug_typ' => '',
            'bezug_id' => '',
        ]);

        self::assertSame(1, $this->countAll());
        self::assertNull($this->appointments()->findRecent()->subject());
    }

    #[Test]
    public function the_new_appointment_belongs_to_whoever_created_it(): void
    {
        // Ohne Besitzer waere der Termin fuer den Ersteller selbst unsichtbar,
        // sobald der Sichtbarkeitsfilter greift.
        $this->submit(['beginn' => '2026-08-20T14:00', 'ende' => '2026-08-20T15:00']);

        self::assertNotNull($this->appointments()->findRecent()->ownerId());
        self::assertNotNull($this->appointments()->findRecent()->ownerTeamId());
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(array $fields, bool $withEnd = true): void
    {
        $this->client->request('POST', '/kalender/neu', [
            'titel' => 'Vor-Ort-Termin',
            'ort' => 'Hamburg',
            ...$fields,
        ] + ($withEnd ? [] : ['ende' => '']));
    }

    private function countAll(): int
    {
        static::getContainer()->get('doctrine')->getManager()->clear();

        return static::getContainer()->get(AppointmentRepositoryInterface::class)->countAll();
    }

    private function appointments(): AppointmentReader
    {
        return new AppointmentReader(
            static::getContainer()->get(AppointmentRepositoryInterface::class),
        );
    }
}

/**
 * Kleiner Helfer: der zuletzt angelegte Termin.
 */
final readonly class AppointmentReader
{
    public function __construct(private AppointmentRepositoryInterface $appointments)
    {
    }

    public function findRecent(): \Crm\Calendar\Domain\Appointment
    {
        $found = $this->appointments->findUpcoming(new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC')), 1);

        \PHPUnit\Framework\Assert::assertNotEmpty($found, 'Es wurde kein Termin angelegt.');

        return $found[0];
    }
}
