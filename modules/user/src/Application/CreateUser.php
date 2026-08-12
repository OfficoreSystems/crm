<?php

declare(strict_types=1);

namespace Crm\User\Application;

use Crm\User\Domain\EmailAlreadyInUse;
use Crm\User\Domain\PasswordHasherInterface;
use Crm\User\Domain\Team;
use Crm\User\Domain\TeamNotFound;
use Crm\User\Domain\TeamRepositoryInterface;
use Crm\User\Domain\User;
use Crm\User\Domain\UserRepositoryInterface;

final readonly class CreateUser
{
    public function __construct(
        private UserRepositoryInterface $users,
        private TeamRepositoryInterface $teams,
        private PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(CreateUserCommand $command): User
    {
        User::assertPasswordIsAcceptable($command->plainPassword);

        $team = $this->resolveTeam($command);

        // Erst konstruieren, dann auf Dubletten pruefen: die Entity
        // normalisiert die Adresse (trim + Kleinschreibung), und genau die
        // normalisierte Form muss verglichen werden. Sonst kaeme " A@X.de "
        // an einer Pruefung gegen "a@x.de" vorbei.
        $user = User::create(
            $command->email,
            $command->name,
            $this->passwordHasher->hash($command->plainPassword),
            $command->roles,
            $team,
        );

        if ($this->users->emailExists($user->email())) {
            throw EmailAlreadyInUse::for($user->email());
        }

        $this->users->save($user);

        return $user;
    }

    private function resolveTeam(CreateUserCommand $command): ?Team
    {
        if (null === $command->teamId) {
            return null;
        }

        return $this->teams->find($command->teamId)
            ?? throw TeamNotFound::withId($command->teamId);
    }
}
