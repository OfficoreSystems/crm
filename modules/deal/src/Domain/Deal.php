<?php

declare(strict_types=1);

namespace Crm\Deal\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Eine Verkaufschance.
 *
 * Die Verweise auf Firma, Kontakt und Besitzer sind skalare UUIDs ohne
 * Fremdschluessel - sie zeigen in andere Module. Aufgeloest werden sie ueber
 * die Finder-Interfaces aus dem Shared Kernel, und dass sie ins Leere zeigen
 * koennen, ist ein normaler Zustand.
 */
#[ORM\Entity]
#[ORM\Table(name: 'deal_deals')]
#[ORM\Index(name: 'idx_deal_stage', columns: ['stage'])]
#[ORM\Index(name: 'idx_deal_company', columns: ['company_id'])]
#[ORM\Index(name: 'idx_deal_owner', columns: ['owner_id'])]
#[ORM\Index(name: 'idx_deal_owner_team', columns: ['owner_team_id'])]
class Deal
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'string', length: 20, enumType: Stage::class)]
    private Stage $stage;

    #[ORM\Embedded(class: Money::class, columnPrefix: 'value_')]
    private Money $value;

    #[ORM\Column(name: 'company_id', type: 'uuid', nullable: true)]
    private ?Uuid $companyId;

    #[ORM\Column(name: 'contact_id', type: 'uuid', nullable: true)]
    private ?Uuid $contactId;

    #[ORM\Column(name: 'owner_id', type: 'uuid', nullable: true)]
    private ?Uuid $ownerId;

    /**
     * Das Team des Besitzers zum Zeitpunkt der Zuweisung.
     *
     * Bewusst am Datensatz gespeichert und nicht ueber den Besitzer
     * aufgeloest. Zwei Gruende: der Doctrine-Filter schraenkt Listen direkt in
     * SQL ein und kann dabei keinen Finder aufrufen - er braucht eine Spalte.
     * Und fachlich ist es richtiger: eine Chance bleibt bei dem Team, das sie
     * bearbeitet hat, auch wenn der Besitzer spaeter wechselt.
     */
    #[ORM\Column(name: 'owner_team_id', type: 'uuid', nullable: true)]
    private ?Uuid $ownerTeamId;

    #[ORM\Column(name: 'expected_close_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expectedCloseDate;

    /**
     * Wird gesetzt, sobald die Chance in einen Endzustand wechselt, und wieder
     * geleert, wenn sie erneut geoeffnet wird. Damit laesst sich "gewonnen im
     * Maerz" beantworten, ohne ein Aenderungsprotokoll zu fuehren.
     */
    #[ORM\Column(name: 'closed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        Uuid $id,
        string $title,
        Stage $stage,
        Money $value,
        ?Uuid $companyId,
        ?Uuid $contactId,
        ?Uuid $ownerId,
        ?Uuid $ownerTeamId,
        ?\DateTimeImmutable $expectedCloseDate,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->title = self::requireTitle($title);
        $this->stage = $stage;
        $this->value = self::requireNonNegative($value);
        $this->companyId = $companyId;
        $this->contactId = $contactId;
        $this->ownerId = $ownerId;
        $this->ownerTeamId = null === $ownerId ? null : $ownerTeamId;
        $this->expectedCloseDate = $expectedCloseDate;
        $this->closedAt = $stage->isClosed() ? $createdAt : null;
        $this->createdAt = $createdAt;
    }

    public static function create(
        string $title,
        ?Money $value = null,
        ?Stage $stage = null,
        ?Uuid $companyId = null,
        ?Uuid $contactId = null,
        ?Uuid $ownerId = null,
        ?Uuid $ownerTeamId = null,
        ?\DateTimeImmutable $expectedCloseDate = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            Uuid::v7(),
            $title,
            $stage ?? Stage::initial(),
            $value ?? Money::zero(),
            $companyId,
            $contactId,
            $ownerId,
            $ownerTeamId,
            $expectedCloseDate,
            $createdAt ?? new \DateTimeImmutable(),
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

    public function stage(): Stage
    {
        return $this->stage;
    }

    public function value(): Money
    {
        return $this->value;
    }

    public function companyId(): ?Uuid
    {
        return $this->companyId;
    }

    public function contactId(): ?Uuid
    {
        return $this->contactId;
    }

    public function ownerId(): ?Uuid
    {
        return $this->ownerId;
    }

    public function ownerTeamId(): ?Uuid
    {
        return $this->ownerTeamId;
    }

    public function expectedCloseDate(): ?\DateTimeImmutable
    {
        return $this->expectedCloseDate;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isOpen(): bool
    {
        return $this->stage->isOpen();
    }

    public function isWon(): bool
    {
        return $this->stage->isWon();
    }

    /**
     * Wechselt die Stufe und pflegt closedAt mit.
     *
     * Es gibt bewusst keine erlaubten Uebergaenge: im Vertrieb springt man
     * zurueck, ueberspringt Stufen und oeffnet Verlorenes wieder. Eine
     * Zustandsmaschine haette hier nur Arbeit gemacht und waere umgangen
     * worden.
     */
    public function moveTo(Stage $stage, ?\DateTimeImmutable $at = null): void
    {
        if ($stage === $this->stage) {
            return;
        }

        $this->stage = $stage;
        $this->closedAt = $stage->isClosed() ? ($at ?? new \DateTimeImmutable()) : null;
    }

    public function rename(string $title): void
    {
        $this->title = self::requireTitle($title);
    }

    public function changeValue(Money $value): void
    {
        $this->value = self::requireNonNegative($value);
    }

    public function assignToCompany(?Uuid $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function assignToContact(?Uuid $contactId): void
    {
        $this->contactId = $contactId;
    }

    /**
     * Besitzer und dessen Team zusammen setzen.
     *
     * Bewusst ein Aufruf statt zweier Setter: ein Besitzer ohne Team oder ein
     * Team ohne Besitzer waere ein halber Zustand, und die Rechtepruefung
     * haengt an beidem.
     */
    public function assignToOwner(?Uuid $ownerId, ?Uuid $ownerTeamId = null): void
    {
        $this->ownerId = $ownerId;
        $this->ownerTeamId = null === $ownerId ? null : $ownerTeamId;
    }

    public function expectToCloseOn(?\DateTimeImmutable $date): void
    {
        $this->expectedCloseDate = $date;
    }

    private static function requireTitle(string $title): string
    {
        $trimmed = trim($title);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Deal.title darf nicht leer sein.');
        }

        return $trimmed;
    }

    private static function requireNonNegative(Money $value): Money
    {
        // Ein negativer Wert waere kein Geschaeft, sondern ein Vorzeichenfehler
        // beim Import - und er wuerde die Pipeline-Summe still verfaelschen.
        if ($value->isNegative()) {
            throw new \InvalidArgumentException('Deal.value darf nicht negativ sein.');
        }

        return $value;
    }
}
