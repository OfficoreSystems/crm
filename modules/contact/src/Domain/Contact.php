<?php

declare(strict_types=1);

namespace Crm\Contact\Domain;

use Crm\Contact\Infrastructure\Doctrine\DoctrineContactRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Kontakt.
 *
 * Der Tabellenname ist mit dem Modulnamen praefixt (`contact_`). Das ist
 * Konvention fuer alle Module: sobald Module von Dritten kommen, ist eine
 * gemeinsame Datenbank ohne Praefix eine Kollisionsfalle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'contact_contacts')]
#[ORM\Index(name: 'idx_contact_last_name', columns: ['last_name'])]
class Contact
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(name: 'first_name', length: 120)]
    private string $firstName;

    #[ORM\Column(name: 'last_name', length: 120)]
    private string $lastName;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $company;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        Uuid $id,
        string $firstName,
        string $lastName,
        ?string $email,
        ?string $company,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->firstName = self::requireNonBlank($firstName, 'firstName');
        $this->lastName = self::requireNonBlank($lastName, 'lastName');
        $this->email = self::normalizeOptional($email);
        $this->company = self::normalizeOptional($company);
        $this->createdAt = $createdAt;
    }

    public static function create(
        string $firstName,
        string $lastName,
        ?string $email = null,
        ?string $company = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            Uuid::v7(),
            $firstName,
            $lastName,
            $email,
            $company,
            $createdAt ?? new \DateTimeImmutable(),
        );
    }

    public function repositoryClass(): string
    {
        return DoctrineContactRepository::class;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function fullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function company(): ?string
    {
        return $this->company;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $firstName, string $lastName): void
    {
        $this->firstName = self::requireNonBlank($firstName, 'firstName');
        $this->lastName = self::requireNonBlank($lastName, 'lastName');
    }

    public function changeEmail(?string $email): void
    {
        $this->email = self::normalizeOptional($email);
    }

    public function changeCompany(?string $company): void
    {
        $this->company = self::normalizeOptional($company);
    }

    private static function requireNonBlank(string $value, string $field): string
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException(sprintf('Contact.%s darf nicht leer sein.', $field));
        }

        return $trimmed;
    }

    private static function normalizeOptional(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }
}
