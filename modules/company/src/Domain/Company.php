<?php

declare(strict_types=1);

namespace Crm\Company\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'company_companies')]
#[ORM\Index(name: 'idx_company_name', columns: ['name'])]
#[ORM\Index(name: 'idx_company_industry', columns: ['industry'])]
class Company
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $name;

    /**
     * Branche. Freitext und nicht Enum, weil Branchenschluessel je nach
     * Geschaeft anders geschnitten sind - eine feste Liste waere nach dem
     * dritten Kunden falsch. Der Index sorgt dafuer, dass Auswertungen
     * trotzdem schnell bleiben.
     */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $industry;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $phone;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'address_')]
    private Address $address;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        Uuid $id,
        string $name,
        ?string $industry,
        ?string $website,
        ?string $email,
        ?string $phone,
        Address $address,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->name = self::requireName($name);
        $this->industry = self::normalizeOptional($industry);
        $this->website = self::normalizeWebsite($website);
        $this->email = self::normalizeEmail($email);
        $this->phone = self::normalizeOptional($phone);
        $this->address = $address;
        $this->createdAt = $createdAt;
    }

    public static function create(
        string $name,
        ?string $industry = null,
        ?string $website = null,
        ?string $email = null,
        ?string $phone = null,
        ?Address $address = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            Uuid::v7(),
            $name,
            $industry,
            $website,
            $email,
            $phone,
            $address ?? Address::empty(),
            $createdAt ?? new \DateTimeImmutable(),
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function industry(): ?string
    {
        return $this->industry;
    }

    public function website(): ?string
    {
        return $this->website;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function address(): Address
    {
        return $this->address;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $name): void
    {
        $this->name = self::requireName($name);
    }

    public function changeIndustry(?string $industry): void
    {
        $this->industry = self::normalizeOptional($industry);
    }

    public function changeWebsite(?string $website): void
    {
        $this->website = self::normalizeWebsite($website);
    }

    public function changeEmail(?string $email): void
    {
        $this->email = self::normalizeEmail($email);
    }

    public function changePhone(?string $phone): void
    {
        $this->phone = self::normalizeOptional($phone);
    }

    public function moveTo(Address $address): void
    {
        $this->address = $address;
    }

    private static function requireName(string $name): string
    {
        $trimmed = trim($name);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Company.name darf nicht leer sein.');
        }

        return $trimmed;
    }

    private static function normalizeOptional(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    private static function normalizeEmail(?string $email): ?string
    {
        $trimmed = mb_strtolower(trim((string) $email));

        if ('' === $trimmed) {
            return null;
        }

        if (!filter_var($trimmed, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('"%s" ist keine gueltige E-Mail-Adresse.', $email));
        }

        return $trimmed;
    }

    /**
     * Ergaenzt ein fehlendes Schema.
     *
     * Ohne das landet "example.test" als Linkziel im Template und der Browser
     * haengt es an die aktuelle URL an - der Link zeigt dann ins eigene CRM.
     */
    private static function normalizeWebsite(?string $website): ?string
    {
        $trimmed = trim((string) $website);

        if ('' === $trimmed) {
            return null;
        }

        if (1 !== preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://'.$trimmed;
        }

        if (!filter_var($trimmed, \FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(sprintf('"%s" ist keine gueltige Adresse.', $website));
        }

        return $trimmed;
    }
}
