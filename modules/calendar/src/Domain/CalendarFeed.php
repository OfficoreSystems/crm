<?php

declare(strict_types=1);

namespace Crm\Calendar\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Der Abonnement-Zugang eines Benutzers zu seinem Kalender.
 *
 * Outlook, Google Calendar und Apple Kalender rufen eine URL ab, ohne sich
 * anzumelden - sie haben keine Sitzung und koennen keine bekommen. Der einzige
 * Ausweis ist also die URL selbst.
 *
 * Daraus folgt alles Weitere:
 *
 *   - Das Geheimnis ist lang und zufaellig, nicht die Benutzer-ID.
 *   - Gespeichert wird nur der Hash. Wer die Datenbank liest, kann damit
 *     keinen Feed abrufen - genau wie bei einem Passwort.
 *   - Es laesst sich jederzeit neu erzeugen. Eine URL, die einmal in einer
 *     E-Mail gelandet ist, bekommt man nicht zurueck; man kann sie nur
 *     ungueltig machen.
 *
 * Der Feed enthaelt deshalb bewusst nur, was dieser eine Benutzer ohnehin
 * sehen darf.
 */
#[ORM\Entity]
#[ORM\Table(name: 'calendar_feeds')]
#[ORM\UniqueConstraint(name: 'uniq_feed_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_feed_token', columns: ['token_hash'])]
class CalendarFeed
{
    /**
     * 32 Byte. Kuerzer waere ratbar, laenger macht die URL nur unhandlicher.
     */
    private const TOKEN_BYTES = 32;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $userId;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Wann der Feed zuletzt abgerufen wurde.
     *
     * Nicht fuer Statistik, sondern damit man erkennt, ob ein Abonnement noch
     * benutzt wird - bevor man eine URL neu erzeugt und sich wundert, warum
     * jemandes Kalender leer ist.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt;

    private function __construct(Uuid $id, Uuid $userId, string $tokenHash)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->tokenHash = $tokenHash;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->lastUsedAt = null;
    }

    /**
     * Legt einen Feed an und gibt das Klartext-Token *einmalig* zurueck.
     *
     * Danach ist es nirgends mehr zu bekommen - gespeichert ist nur der Hash.
     * Wer die URL verliert, erzeugt eine neue.
     *
     * @return array{0: self, 1: string} Feed und Klartext-Token
     */
    public static function issueFor(Uuid $userId): array
    {
        $token = self::randomToken();

        return [new self(Uuid::v7(), $userId, self::hash($token)), $token];
    }

    /**
     * @return string Das neue Klartext-Token
     */
    public function regenerate(): string
    {
        $token = self::randomToken();
        $this->tokenHash = self::hash($token);
        $this->lastUsedAt = null;

        return $token;
    }

    /**
     * Der Hash, nach dem beim Abruf gesucht wird.
     *
     * Deterministisch und ohne Salt - anders als bei einem Passwort ist das
     * hier richtig: gesucht wird *nach* dem Wert, und das Token ist bereits
     * 32 zufaellige Bytes. Eine Rainbow Table dafuer gibt es nicht.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function randomToken(): string
    {
        // base64url: passt ohne Kodierung in eine URL. rtrim entfernt das
        // Fuellzeichen "=", das in URLs nur Aerger macht.
        return rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
    }
}
