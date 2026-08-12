<?php

declare(strict_types=1);

namespace Crm\User\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'user_teams')]
class Team
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 120, unique: true)]
    private string $name;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(Uuid $id, string $name, \DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->name = self::requireName($name);
        $this->createdAt = $createdAt;
    }

    public static function create(string $name, ?\DateTimeImmutable $createdAt = null): self
    {
        return new self(Uuid::v7(), $name, $createdAt ?? new \DateTimeImmutable());
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $name): void
    {
        $this->name = self::requireName($name);
    }

    private static function requireName(string $name): string
    {
        $trimmed = trim($name);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Team.name darf nicht leer sein.');
        }

        return $trimmed;
    }
}
