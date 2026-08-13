<?php

declare(strict_types=1);

namespace Crm\User\Domain;

use Crm\SharedKernel\Localization\Locale;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Benutzer.
 *
 * Die Klasse implementiert bewusst **nicht** Symfonys UserInterface. Sonst
 * haengt die Domain am Security-Paket, und "Domain haengt an nichts" waere
 * nur noch eine Behauptung. Die Bruecke zu Symfony ist
 * {@see \Crm\User\Infrastructure\Security\SecurityUser} in der
 * Infrastructure-Schicht.
 *
 * Die Association auf Team ist erlaubt, weil beide Entitaeten im selben Modul
 * liegen. Ueber Modulgrenzen hinweg waere sie es nicht - dort stehen skalare
 * IDs und Finder-Interfaces aus dem Shared Kernel.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_users')]
#[ORM\Index(name: 'idx_user_team', columns: ['team_id'])]
class User
{
    public const MINIMUM_PASSWORD_LENGTH = 12;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 120)]
    private string $name;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Team $team;

    #[ORM\Column]
    private bool $active;

    /**
     * Die gewaehlte Sprache, oder null.
     *
     * Nullable und nicht "en" als Vorgabe: null heisst "nie gewaehlt". Der
     * Unterschied zaehlt, wenn sich die Standardsprache der Anwendung einmal
     * aendert - dann wandern alle mit, die nie etwas ausgesucht haben, und nur
     * die.
     */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $locale;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<Role> $roles
     */
    private function __construct(
        Uuid $id,
        string $email,
        string $name,
        array $roles,
        string $passwordHash,
        ?Team $team,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->email = self::normalizeEmail($email);
        $this->name = self::requireName($name);
        $this->roles = self::normalizeRoles($roles);
        $this->passwordHash = $passwordHash;
        $this->team = $team;
        $this->active = true;
        $this->locale = null;
        $this->createdAt = $createdAt;
    }

    /**
     * @param list<Role> $roles
     * @param string     $passwordHash Bereits gehasht - die Domain hasht nicht
     *                                 selbst, das ist Infrastruktur.
     */
    public static function create(
        string $email,
        string $name,
        string $passwordHash,
        array $roles = [],
        ?Team $team = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        if ('' === trim($passwordHash)) {
            throw new \InvalidArgumentException('User.passwordHash darf nicht leer sein.');
        }

        return new self(
            Uuid::v7(),
            $email,
            $name,
            $roles,
            $passwordHash,
            $team,
            $createdAt ?? new \DateTimeImmutable(),
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    public function hasRole(Role $role): bool
    {
        return \in_array($role->value, $this->roles, true);
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function team(): ?Team
    {
        return $this->team;
    }

    public function teamId(): ?Uuid
    {
        return $this->team?->id();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Null, solange nie eine Sprache gewaehlt wurde.
     */
    public function locale(): ?Locale
    {
        return null === $this->locale ? null : Locale::tryFrom($this->locale);
    }

    public function switchTo(Locale $locale): void
    {
        $this->locale = $locale->value;
    }

    public function changeEmail(string $email): void
    {
        $this->email = self::normalizeEmail($email);
    }

    public function rename(string $name): void
    {
        $this->name = self::requireName($name);
    }

    public function changePasswordHash(string $passwordHash): void
    {
        if ('' === trim($passwordHash)) {
            throw new \InvalidArgumentException('User.passwordHash darf nicht leer sein.');
        }

        $this->passwordHash = $passwordHash;
    }

    /**
     * @param list<Role> $roles
     */
    public function changeRoles(array $roles): void
    {
        $this->roles = self::normalizeRoles($roles);
    }

    public function joinTeam(?Team $team): void
    {
        $this->team = $team;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    /**
     * Prueft eine Passwort-Eingabe, bevor sie gehasht wird. Steht hier und
     * nicht im Formular, damit die Regel auch fuer den Konsolenbefehl gilt.
     */
    public static function assertPasswordIsAcceptable(string $plainPassword): void
    {
        if (mb_strlen($plainPassword) < self::MINIMUM_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Das Passwort muss mindestens %d Zeichen haben.',
                self::MINIMUM_PASSWORD_LENGTH,
            ));
        }
    }

    private static function normalizeEmail(string $email): string
    {
        $trimmed = mb_strtolower(trim($email));

        if (!filter_var($trimmed, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('"%s" ist keine gueltige E-Mail-Adresse.', $email));
        }

        return $trimmed;
    }

    private static function requireName(string $name): string
    {
        $trimmed = trim($name);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('User.name darf nicht leer sein.');
        }

        return $trimmed;
    }

    /**
     * @param list<Role> $roles
     *
     * @return list<string>
     */
    private static function normalizeRoles(array $roles): array
    {
        $values = array_map(static fn (Role $role): string => $role->value, $roles);

        // ROLE_USER ist immer dabei. Ohne sie waere ein Benutzer angemeldet,
        // haette aber keine einzige Rolle - ein Zustand, den niemand erwartet.
        $values[] = Role::baseline()->value;

        return array_values(array_unique($values));
    }
}
