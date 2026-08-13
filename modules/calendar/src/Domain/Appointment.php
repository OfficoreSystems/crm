<?php

declare(strict_types=1);

namespace Crm\Calendar\Domain;

use Crm\SharedKernel\Subject\SubjectRef;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Termin.
 *
 * Kann an einem Datensatz haengen - Typ plus ID, kein Fremdschluessel, wie bei
 * Aktivitaeten und Dokumenten. Muss aber nicht: ein Teammeeting gehoert zu
 * niemandem im CRM.
 *
 * Alle Zeitangaben sind UTC. Das erzwingt {@see TimeSpan}, und es steht nicht
 * zur Wahl: Termine wandern per ICS in fremde Kalender, und dort gibt es
 * niemanden mehr, den man nach der gemeinten Zeitzone fragen koennte.
 */
#[ORM\Entity]
#[ORM\Table(name: 'calendar_appointments')]
#[ORM\Index(name: 'idx_appointment_starts_at', columns: ['starts_at'])]
#[ORM\Index(name: 'idx_appointment_subject', columns: ['subject_type', 'subject_id'])]
#[ORM\Index(name: 'idx_appointment_owner', columns: ['owner_id'])]
class Appointment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $location;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column]
    private bool $allDay;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $subjectType;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $subjectId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $ownerId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $ownerTeamId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Wird bei jeder Aenderung hochgezaehlt.
     *
     * RFC 5545 verlangt das: ohne steigende SEQUENCE ignorieren viele Clients
     * die Aktualisierung und zeigen weiter den alten Termin an.
     */
    #[ORM\Column]
    private int $sequence;

    private function __construct(
        Uuid $id,
        string $title,
        TimeSpan $when,
        ?string $description,
        ?string $location,
        ?SubjectRef $subject,
        ?Uuid $ownerId,
        ?Uuid $ownerTeamId,
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->location = $location;
        $this->startsAt = $when->start;
        $this->endsAt = $when->end;
        $this->allDay = $when->allDay;
        $this->subjectType = $subject?->type;
        $this->subjectId = $subject?->id;
        $this->ownerId = $ownerId;
        $this->ownerTeamId = $ownerTeamId;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->sequence = 0;
    }

    public static function schedule(
        string $title,
        TimeSpan $when,
        ?string $description = null,
        ?string $location = null,
        ?SubjectRef $subject = null,
        ?Uuid $ownerId = null,
        ?Uuid $ownerTeamId = null,
    ): self {
        $title = trim($title);

        if ('' === $title) {
            throw new \InvalidArgumentException('Ein Termin ohne Titel ist in keinem Kalender wiederzufinden.');
        }

        return new self(
            id: Uuid::v7(),
            title: mb_substr($title, 0, 200),
            when: $when,
            description: self::emptyToNull($description),
            location: self::emptyToNull($location, 200),
            subject: $subject,
            ownerId: $ownerId,
            ownerTeamId: $ownerTeamId,
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function when(): TimeSpan
    {
        return $this->allDay
            ? TimeSpan::allDay($this->startsAt, max(1, intdiv($this->endsAt->getTimestamp() - $this->startsAt->getTimestamp(), 86400)))
            : TimeSpan::of($this->startsAt, $this->endsAt);
    }

    public function startsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function subject(): ?SubjectRef
    {
        return null !== $this->subjectType && null !== $this->subjectId
            ? new SubjectRef($this->subjectType, $this->subjectId)
            : null;
    }

    public function ownerId(): ?Uuid
    {
        return $this->ownerId;
    }

    public function ownerTeamId(): ?Uuid
    {
        return $this->ownerTeamId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function reschedule(TimeSpan $when): void
    {
        if ($when->start->getTimestamp() === $this->startsAt->getTimestamp()
            && $when->end->getTimestamp() === $this->endsAt->getTimestamp()
            && $when->allDay === $this->allDay
        ) {
            // Keine Aenderung, keine neue SEQUENCE: sonst blinken die Termine
            // in fremden Kalendern bei jedem Speichern auf.
            return;
        }

        $this->startsAt = $when->start;
        $this->endsAt = $when->end;
        $this->allDay = $when->allDay;
        ++$this->sequence;
    }

    public function rename(string $title, ?string $description = null, ?string $location = null): void
    {
        $title = trim($title);

        if ('' === $title) {
            throw new \InvalidArgumentException('Ein Termin ohne Titel ist in keinem Kalender wiederzufinden.');
        }

        $this->title = mb_substr($title, 0, 200);
        $this->description = self::emptyToNull($description);
        $this->location = self::emptyToNull($location, 200);
        ++$this->sequence;
    }

    private static function emptyToNull(?string $value, ?int $maxLength = null): ?string
    {
        $value = trim($value ?? '');

        if ('' === $value) {
            return null;
        }

        return null === $maxLength ? $value : mb_substr($value, 0, $maxLength);
    }
}
