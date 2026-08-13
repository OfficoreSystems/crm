<?php

declare(strict_types=1);

namespace Crm\Calendar\Tests\Infrastructure;

use Crm\Calendar\Domain\Appointment;
use Crm\Calendar\Domain\AppointmentRepositoryInterface;
use Crm\Calendar\Domain\TimeSpan;
use Crm\SharedKernel\Subject\SubjectRef;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Gegen die echte Datenbank.
 *
 * Der wichtigste Punkt hier ist der Rundweg der Zeit: was als Berliner
 * Nachmittag hineingeht, muss als UTC-Mittag herauskommen. Ein In-Memory-Double
 * koennte das gar nicht falsch machen.
 */
final class DoctrineAppointmentRepositoryTest extends KernelTestCase
{
    private AppointmentRepositoryInterface $appointments;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->appointments = static::getContainer()->get(AppointmentRepositoryInterface::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();

        parent::tearDown();
    }

    private function purge(): void
    {
        $this->entityManager->getConnection()->executeStatement('DELETE FROM calendar_appointments');
        $this->entityManager->clear();
    }

    #[Test]
    public function a_local_time_comes_back_as_the_same_instant(): void
    {
        $berlin = new \DateTimeImmutable('2026-08-20 14:00:00', new \DateTimeZone('Europe/Berlin'));
        $appointment = $this->given('Vor-Ort-Termin', TimeSpan::of($berlin, $berlin->modify('+90 minutes')));

        $this->entityManager->clear();
        $found = $this->appointments->find($appointment->id());

        self::assertNotNull($found);
        self::assertSame($berlin->getTimestamp(), $found->startsAt()->getTimestamp());
        self::assertSame('2026-08-20 12:00:00', $found->startsAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function the_database_stores_utc_and_not_local_time(): void
    {
        // Direkt in der Spalte nachgesehen. Stuende hier 14:00, waere der
        // Wert von der Zeitzone des Servers abhaengig - und ein Umzug der
        // Anwendung wuerde alle Termine verschieben.
        $berlin = new \DateTimeImmutable('2026-08-20 14:00:00', new \DateTimeZone('Europe/Berlin'));
        $this->given('Vor-Ort-Termin', TimeSpan::of($berlin, $berlin->modify('+1 hour')));

        $stored = $this->entityManager->getConnection()
            ->fetchOne('SELECT starts_at FROM calendar_appointments LIMIT 1');

        self::assertStringStartsWith('2026-08-20 12:00:00', (string) $stored);
    }

    #[Test]
    public function the_all_day_flag_survives_the_round_trip(): void
    {
        $appointment = $this->given(
            'Betriebsausflug',
            TimeSpan::allDay(new \DateTimeImmutable('2026-08-20 00:00:00', new \DateTimeZone('UTC')), 2),
        );

        $this->entityManager->clear();
        $found = $this->appointments->find($appointment->id());

        self::assertNotNull($found);
        self::assertTrue($found->isAllDay());
        self::assertTrue($found->when()->allDay);
        self::assertSame('2026-08-22', $found->when()->end->format('Y-m-d'));
    }

    #[Test]
    public function an_appointment_reaching_into_the_window_is_found(): void
    {
        // Ueberschneidung, nicht Enthaltensein: ein Termin von Freitag bis
        // Montag gehoert in die Wochenansicht beider Wochen.
        $this->given('Messe', TimeSpan::of($this->at('2026-08-14 08:00'), $this->at('2026-08-17 18:00')));

        $secondWeek = $this->appointments->findBetween($this->at('2026-08-17 00:00'), $this->at('2026-08-24 00:00'));

        self::assertCount(1, $secondWeek);
    }

    #[Test]
    public function an_appointment_outside_the_window_is_not_found(): void
    {
        $this->given('Messe', TimeSpan::of($this->at('2026-08-14 08:00'), $this->at('2026-08-14 18:00')));

        self::assertSame([], $this->appointments->findBetween($this->at('2026-08-17 00:00'), $this->at('2026-08-24 00:00')));
    }

    #[Test]
    public function a_running_appointment_still_counts_as_upcoming(): void
    {
        // Wer um 10:30 auf den Kalender schaut, will den Termin von 10 bis 11
        // noch sehen - er laeuft ja gerade.
        $this->given('Jour fixe', TimeSpan::of($this->at('2026-08-20 10:00'), $this->at('2026-08-20 11:00')));

        $upcoming = $this->appointments->findUpcoming($this->at('2026-08-20 10:30'));

        self::assertCount(1, $upcoming);
    }

    #[Test]
    public function a_finished_appointment_is_not_upcoming(): void
    {
        $this->given('Jour fixe', TimeSpan::of($this->at('2026-08-20 10:00'), $this->at('2026-08-20 11:00')));

        self::assertSame([], $this->appointments->findUpcoming($this->at('2026-08-20 11:00')));
    }

    #[Test]
    public function upcoming_appointments_come_in_chronological_order(): void
    {
        // Ein Kalender liest sich vorwaerts.
        $this->given('Spaeter', TimeSpan::of($this->at('2026-08-22 10:00'), $this->at('2026-08-22 11:00')));
        $this->given('Frueher', TimeSpan::of($this->at('2026-08-20 10:00'), $this->at('2026-08-20 11:00')));

        $upcoming = $this->appointments->findUpcoming($this->at('2026-08-01 00:00'));

        self::assertSame('Frueher', $upcoming[0]->title());
        self::assertSame('Spaeter', $upcoming[1]->title());
    }

    #[Test]
    public function the_feed_query_returns_only_the_appointments_of_one_owner(): void
    {
        // DER Test fuer den ICS-Feed: dort ist der Doctrine-Sichtbarkeitsfilter
        // abgeschaltet, weil es keinen angemeldeten Benutzer gibt. Die
        // Einschraenkung muss aus dieser Abfrage kommen und sonst nirgendwoher.
        $vera = Uuid::v7();
        $ingo = Uuid::v7();

        $this->given('Veras Termin', $this->span(), ownerId: $vera);
        $this->given('Ingos Termin', $this->span(), ownerId: $ingo);
        $this->given('Herrenloser Termin', $this->span());

        $found = $this->appointments->findForOwnerBetween($vera, $this->at('2026-08-01 00:00'), $this->at('2026-09-01 00:00'));

        self::assertCount(1, $found);
        self::assertSame('Veras Termin', $found[0]->title());
    }

    #[Test]
    public function it_finds_the_appointments_of_one_subject(): void
    {
        $this->given('Beim Kontakt', $this->span(), subject: new SubjectRef('contact', 'kontakt-1'));
        $this->given('Bei der Firma', $this->span(), subject: new SubjectRef('company', 'kontakt-1'));

        $found = $this->appointments->findForSubject(new SubjectRef('contact', 'kontakt-1'));

        self::assertCount(1, $found, 'Gleiche ID unter anderem Typ ist ein anderes Subjekt.');
        self::assertSame('Beim Kontakt', $found[0]->title());
    }

    #[Test]
    public function it_counts_and_removes(): void
    {
        $appointment = $this->given('Jour fixe', $this->span());

        self::assertSame(1, $this->appointments->countAll());
        self::assertSame(1, $this->appointments->countUpcoming($this->at('2026-08-01 00:00')));

        $this->appointments->remove($appointment);

        self::assertSame(0, $this->appointments->countAll());
        self::assertNull($this->appointments->find($appointment->id()));
    }

    private function given(
        string $title,
        TimeSpan $when,
        ?Uuid $ownerId = null,
        ?SubjectRef $subject = null,
    ): Appointment {
        $appointment = Appointment::schedule(
            title: $title,
            when: $when,
            subject: $subject,
            ownerId: $ownerId,
        );

        $this->appointments->save($appointment);

        return $appointment;
    }

    private function span(): TimeSpan
    {
        return TimeSpan::of($this->at('2026-08-20 10:00'), $this->at('2026-08-20 11:00'));
    }

    private function at(string $moment): \DateTimeImmutable
    {
        return new \DateTimeImmutable($moment, new \DateTimeZone('UTC'));
    }
}
