<?php

declare(strict_types=1);

namespace Crm\Activity\Domain;

use Crm\SharedKernel\Subject\SubjectRef;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Eintrag in der Timeline: Notiz, Anruf, Termin oder Aufgabe.
 *
 * Der Bezug ist polymorph - subject_type und subject_id zusammen. Welche
 * Typen es gibt, weiss dieses Modul nicht; es speichert die Zeichenkette und
 * ueberlaesst das Aufloesen der SubjectResolverRegistry.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_activities')]
#[ORM\Index(name: 'idx_activity_subject', columns: ['subject_type', 'subject_id'])]
#[ORM\Index(name: 'idx_activity_occurred', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_activity_author_team', columns: ['author_team_id'])]
class Activity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 20, enumType: ActivityType::class)]
    private ActivityType $type;

    #[ORM\Column(name: 'subject_type', length: 40)]
    private string $subjectType;

    #[ORM\Column(name: 'subject_id', length: 64)]
    private string $subjectId;

    #[ORM\Column(length: 200)]
    private string $summary;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body;

    #[ORM\Column(name: 'author_id', type: 'uuid', nullable: true)]
    private ?Uuid $authorId;

    /**
     * Das Team des Autors zum Zeitpunkt des Eintrags.
     *
     * Am Datensatz gespeichert, nicht ueber den Autor aufgeloest: der
     * Doctrine-Filter schraenkt Listen in SQL ein und braucht dafuer eine
     * Spalte. Fachlich passt es ohnehin - ein Gespraechsprotokoll gehoert dem
     * Team, das es gefuehrt hat.
     */
    #[ORM\Column(name: 'author_team_id', type: 'uuid', nullable: true)]
    private ?Uuid $authorTeamId;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        Uuid $id,
        ActivityType $type,
        SubjectRef $subject,
        string $summary,
        ?string $body,
        ?Uuid $authorId,
        ?Uuid $authorTeamId,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->subjectType = $subject->type;
        $this->subjectId = $subject->id;
        $this->summary = self::requireSummary($summary);
        $this->body = self::normalizeBody($body);
        $this->authorId = $authorId;
        $this->authorTeamId = null === $authorId ? null : $authorTeamId;
        $this->occurredAt = $occurredAt;
        $this->completedAt = null;
        $this->createdAt = $createdAt;
    }

    public static function log(
        ActivityType $type,
        SubjectRef $subject,
        string $summary,
        ?string $body = null,
        ?Uuid $authorId = null,
        ?Uuid $authorTeamId = null,
        ?\DateTimeImmutable $occurredAt = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $now = $createdAt ?? new \DateTimeImmutable();

        return new self(
            Uuid::v7(),
            $type,
            $subject,
            $summary,
            $body,
            $authorId,
            $authorTeamId,
            $occurredAt ?? $now,
            $now,
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function type(): ActivityType
    {
        return $this->type;
    }

    public function subject(): SubjectRef
    {
        return new SubjectRef($this->subjectType, $this->subjectId);
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function authorId(): ?Uuid
    {
        return $this->authorId;
    }

    public function authorTeamId(): ?Uuid
    {
        return $this->authorTeamId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCompleted(): bool
    {
        return null !== $this->completedAt;
    }

    public function isOpenTask(): bool
    {
        return $this->type->isCompletable() && !$this->isCompleted();
    }

    public function isOverdue(?\DateTimeImmutable $now = null): bool
    {
        return $this->isOpenTask() && $this->occurredAt < ($now ?? new \DateTimeImmutable());
    }

    public function complete(?\DateTimeImmutable $at = null): void
    {
        if (!$this->type->isCompletable()) {
            throw new \DomainException(sprintf(
                'Nur Aufgaben lassen sich abhaken, "%s" nicht.',
                $this->type->label(),
            ));
        }

        $this->completedAt = $at ?? new \DateTimeImmutable();
    }

    public function reopen(): void
    {
        $this->completedAt = null;
    }

    public function rewrite(string $summary, ?string $body = null): void
    {
        $this->summary = self::requireSummary($summary);
        $this->body = self::normalizeBody($body);
    }

    /**
     * Haengt den Eintrag an ein anderes Subjekt.
     *
     * Geprueft wird die Existenz hier nicht - dafuer muesste die Domain die
     * Resolver kennen. Das erledigt der Use-Case.
     */
    public function moveTo(SubjectRef $subject): void
    {
        $this->subjectType = $subject->type;
        $this->subjectId = $subject->id;
    }

    private static function requireSummary(string $summary): string
    {
        $trimmed = trim($summary);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Activity.summary darf nicht leer sein.');
        }

        return $trimmed;
    }

    private static function normalizeBody(?string $body): ?string
    {
        $trimmed = trim((string) $body);

        return '' === $trimmed ? null : $trimmed;
    }
}
