<?php

declare(strict_types=1);

namespace Crm\User\UI\Component;

use Crm\User\Domain\User;
use Crm\User\Domain\UserRepositoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'UserList', template: '@UserModule/components/UserList.html.twig')]
final class UserList
{
    use DefaultActionTrait;

    private const LIMIT = 50;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<User>
     */
    public function getUsers(): array
    {
        return $this->repository->search($this->query, self::LIMIT);
    }

    public function getTotal(): int
    {
        return $this->repository->countAll();
    }

    public function isFiltered(): bool
    {
        return '' !== trim($this->query);
    }

    public function isTruncated(): bool
    {
        return \count($this->getUsers()) >= self::LIMIT;
    }
}
