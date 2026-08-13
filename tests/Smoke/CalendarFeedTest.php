<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Crm\Calendar\Application\ScheduleAppointment;
use Crm\Calendar\Application\ScheduleAppointmentCommand;
use Crm\Calendar\Application\SubscribeToCalendar;
use Crm\Calendar\Domain\TimeSpan;
use Crm\User\Domain\User;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Die einzige Seite dieser Anwendung ohne Anmeldung.
 *
 * Damit ist sie auch die einzige, bei der ein Fehler sofort oeffentlich ist.
 * Zwei Dinge muessen stimmen, und beide sind hier festgehalten:
 *
 *   1. Die Firewall laesst den Feed durch - sonst bekommt Outlook die
 *      Anmeldeseite und zeigt einen leeren Kalender, ohne sich zu beschweren.
 *   2. Der Feed zeigt ausschliesslich die Termine seines Besitzers. Der
 *      Doctrine-Sichtbarkeitsfilter hilft hier *nicht*: ohne angemeldeten
 *      Benutzer ist er abgeschaltet.
 */
final class CalendarFeedTest extends WebTestCase
{
    use SignsIn;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->purge(['calendar_appointments', 'calendar_feeds']);
    }

    protected function tearDown(): void
    {
        $this->purge(['calendar_appointments', 'calendar_feeds']);

        parent::tearDown();
    }

    #[Test]
    public function the_feed_is_reachable_without_any_login(): void
    {
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: 'Vertrieb');
        $this->givenAppointment($vera, 'Vor-Ort-Termin bei Nordwind');
        $url = $this->feedUrlFor($vera);

        // Ausdruecklich ein frischer Client ohne Sitzung - so wie Outlook.
        self::ensureKernelShutdown();
        $anonymous = static::createClient();
        $anonymous->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/calendar', (string) $anonymous->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('Vor-Ort-Termin bei Nordwind', (string) $anonymous->getResponse()->getContent());
    }

    #[Test]
    public function a_feed_never_contains_someone_elses_appointments(): void
    {
        // DER Test. Ohne die ausdrueckliche Einschraenkung in der Abfrage
        // lieferte diese URL die Termine *aller* Benutzer aus - und niemand
        // haette es gemerkt, weil der eigene Kalender richtig aussieht.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: 'Vertrieb');
        $ingo = $this->givenUser('ingo@example.test', 'Ingo', teamName: 'Innendienst');

        $this->givenAppointment($vera, 'Veras Termin');
        $this->givenAppointment($ingo, 'Ingos Termin');

        $content = $this->fetch($this->feedUrlFor($vera));

        self::assertStringContainsString('Veras Termin', $content);
        self::assertStringNotContainsString('Ingos Termin', $content);
    }

    #[Test]
    public function a_teammates_appointment_is_not_in_the_feed_either(): void
    {
        // Der Feed ist persoenlich, nicht teamweit. In der Oberflaeche sieht
        // Vitali Veras Termine - in seinem Abonnement nicht. Das ist Absicht:
        // die URL ist ein Geheimnis, das weitergereicht werden kann, und der
        // engere Schnitt ist der, den man spaeter weiten kann.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: 'Vertrieb');
        $vitali = $this->givenUser('vitali@example.test', 'Vitali', teamName: 'Vertrieb');

        $this->givenAppointment($vera, 'Veras Termin');

        self::assertStringNotContainsString('Veras Termin', $this->fetch($this->feedUrlFor($vitali)));
    }

    #[Test]
    public function an_unknown_token_gets_a_plain_404(): void
    {
        // Ohne Erklaerung: ein "Token abgelaufen" waere die Bestaetigung, dass
        // es das Token einmal gab.
        $this->fetchRaw('/oeffentlich/kalender/'.str_repeat('A', 43).'.ics');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function regenerating_the_address_makes_the_old_one_worthless(): void
    {
        // Der einzige Weg, eine einmal verteilte URL wieder einzufangen.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: 'Vertrieb');
        $this->givenAppointment($vera, 'Veras Termin');

        $old = $this->feedUrlFor($vera);
        self::assertStringContainsString('Veras Termin', $this->fetch($old));

        static::getContainer()->get(SubscribeToCalendar::class)->regenerate($vera->id());

        $this->fetchRaw($old);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function the_feed_is_not_cached_and_not_indexed(): void
    {
        // Die URL ist ein Geheimnis. In einem gemeinsamen Cache oder in einem
        // Suchindex hat sie nichts verloren.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: 'Vertrieb');
        $this->givenAppointment($vera, 'Veras Termin');

        $this->fetchRaw($this->feedUrlFor($vera));
        $headers = $this->client->getResponse()->headers;

        self::assertStringContainsString('no-store', (string) $headers->get('Cache-Control'));
        self::assertStringContainsString('noindex', (string) $headers->get('X-Robots-Tag'));
    }

    #[Test]
    public function the_subscription_page_shows_the_address_only_once(): void
    {
        // Gespeichert ist nur der Hash. Waere die URL erneut abrufbar, waere
        // der Hash Dekoration.
        $vera = $this->givenUser('vera@example.test', 'Vera', teamName: 'Vertrieb');
        $this->signIn($this->client, $vera);

        $this->client->request('GET', '/kalender/abonnement');
        self::assertSelectorExists('.feed-url');

        $this->client->request('GET', '/kalender/abonnement');
        self::assertSelectorNotExists('.feed-url');
        self::assertSelectorTextContains('body', 'nicht erneut anzeigen');
    }

    private function givenAppointment(User $owner, string $title): void
    {
        (static::getContainer()->get(ScheduleAppointment::class))(new ScheduleAppointmentCommand(
            title: $title,
            when: TimeSpan::of(
                new \DateTimeImmutable('+2 days 10:00', new \DateTimeZone('UTC')),
                new \DateTimeImmutable('+2 days 11:00', new \DateTimeZone('UTC')),
            ),
            ownerId: $owner->id(),
            ownerTeamId: $owner->teamId(),
        ));
    }

    private function feedUrlFor(User $user): string
    {
        [, $token] = (static::getContainer()->get(SubscribeToCalendar::class))($user->id());

        self::assertNotNull($token, 'Beim ersten Abonnieren muss es ein Token geben.');

        return '/oeffentlich/kalender/'.$token.'.ics';
    }

    private function fetch(string $url): string
    {
        $this->fetchRaw($url);

        return (string) $this->client->getResponse()->getContent();
    }

    private function fetchRaw(string $url): void
    {
        // Der Identity-Map-Grund wie in SignsIn: der erste Request laeuft ohne
        // Kernel-Neustart, und dann kaemen die soeben angelegten Termine aus
        // dem Speicher statt aus der Datenbank.
        static::getContainer()->get('doctrine')->getManager()->clear();

        $this->client->request('GET', $url);
    }
}
