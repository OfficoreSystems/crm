<?php

declare(strict_types=1);

namespace Crm\Document\Domain;

use Crm\SharedKernel\Subject\SubjectRef;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Eine Datei, die an einem beliebigen Datensatz haengt.
 *
 * Der Verweis ist polymorph und skalar: Typ plus ID, kein Fremdschluessel.
 * Genau wie bei Aktivitaeten - das Modul weiss nicht, was ein Kontakt oder
 * eine Verkaufschance ist, und soll es auch nicht wissen.
 *
 * Die Datei selbst liegt im Objektspeicher, hier steht nur, wo. Das ist
 * Absicht: Dateien in der Datenbank machen Backups gross und Migrationen
 * langsam, und ein Objektspeicher kann Dinge, die eine Tabelle nicht kann -
 * etwa eine Datei ausliefern, ohne sie durch PHP zu schleusen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'document_documents')]
#[ORM\Index(name: 'idx_document_subject', columns: ['subject_type', 'subject_id'])]
#[ORM\Index(name: 'idx_document_owner', columns: ['owner_id'])]
class Document
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 40)]
    private string $subjectType;

    #[ORM\Column(length: 64)]
    private string $subjectId;

    /**
     * Der Name, den der Benutzer sieht - nicht der Schluessel im Speicher.
     */
    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 127)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    /**
     * Der Schluessel im Objektspeicher. Nie aus dem Dateinamen abgeleitet:
     * zwei Benutzer duerfen "Angebot.pdf" hochladen, ohne sich gegenseitig zu
     * ueberschreiben, und ein Dateiname aus einer Anfrage ist nichts, worauf
     * man einen Pfad baut.
     */
    #[ORM\Column(length: 255, unique: true)]
    private string $storageKey;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $ownerId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $ownerTeamId;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    private function __construct(
        Uuid $id,
        SubjectRef $subject,
        string $filename,
        string $mimeType,
        int $size,
        string $storageKey,
        ?Uuid $ownerId,
        ?Uuid $ownerTeamId,
        \DateTimeImmutable $uploadedAt,
    ) {
        $this->id = $id;
        $this->subjectType = $subject->type;
        $this->subjectId = $subject->id;
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->storageKey = $storageKey;
        $this->ownerId = $ownerId;
        $this->ownerTeamId = $ownerTeamId;
        $this->uploadedAt = $uploadedAt;
    }

    public static function record(
        SubjectRef $subject,
        string $filename,
        string $mimeType,
        int $size,
        string $storageKey,
        ?Uuid $ownerId = null,
        ?Uuid $ownerTeamId = null,
        ?\DateTimeImmutable $uploadedAt = null,
    ): self {
        $filename = SafeFilename::from($filename);

        if ($size < 1) {
            throw new \InvalidArgumentException('Eine Datei ohne Inhalt wird nicht gespeichert.');
        }

        if ('' === trim($storageKey)) {
            throw new \InvalidArgumentException('Ohne Speicherschluessel liesse sich die Datei nie wiederfinden.');
        }

        return new self(
            id: Uuid::v7(),
            subject: $subject,
            filename: $filename,
            mimeType: '' === trim($mimeType) ? 'application/octet-stream' : $mimeType,
            size: $size,
            storageKey: $storageKey,
            ownerId: $ownerId,
            ownerTeamId: $ownerTeamId,
            uploadedAt: $uploadedAt ?? new \DateTimeImmutable(),
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function subject(): SubjectRef
    {
        return new SubjectRef($this->subjectType, $this->subjectId);
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function ownerId(): ?Uuid
    {
        return $this->ownerId;
    }

    public function ownerTeamId(): ?Uuid
    {
        return $this->ownerTeamId;
    }

    public function uploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    /**
     * Umbenennen aendert nur die Anzeige. Der Schluessel im Speicher bleibt -
     * sonst muesste jede Umbenennung die Datei kopieren, und ein
     * Abbruch dazwischen liesse einen Eintrag ohne Datei zurueck.
     */
    public function renameTo(string $filename): void
    {
        $this->filename = SafeFilename::from($filename);
    }
}
