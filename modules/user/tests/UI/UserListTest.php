<?php

declare(strict_types=1);

namespace Crm\User\Tests\UI;

use Crm\User\Domain\User;
use Crm\User\Tests\Double\InMemoryUserRepository;
use Crm\User\UI\Component\UserList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserListTest extends TestCase
{
    #[Test]
    public function it_lists_everything_without_a_query(): void
    {
        $component = $this->componentWith(3);

        self::assertCount(3, $component->getUsers());
        self::assertSame(3, $component->getTotal());
        self::assertFalse($component->isFiltered());
    }

    #[Test]
    public function it_narrows_the_list_down_to_the_query(): void
    {
        $component = $this->componentWith(3);
        $component->query = 'benutzer2';

        self::assertCount(1, $component->getUsers());
        self::assertSame(3, $component->getTotal(), 'Der Gesamtwert bleibt: das Template zeigt "1 von 3".');
        self::assertTrue($component->isFiltered());
    }

    #[Test]
    public function a_whitespace_query_does_not_count_as_filtered(): void
    {
        $component = $this->componentWith(1);
        $component->query = '   ';

        self::assertFalse($component->isFiltered());
    }

    #[Test]
    public function a_short_list_is_not_truncated(): void
    {
        self::assertFalse($this->componentWith(5)->isTruncated());
    }

    #[Test]
    public function a_full_page_is_reported_as_truncated(): void
    {
        self::assertTrue($this->componentWith(50)->isTruncated());
    }

    private function componentWith(int $count): UserList
    {
        $repository = new InMemoryUserRepository();

        for ($i = 1; $i <= $count; ++$i) {
            $repository->save(User::create(sprintf('benutzer%d@example.test', $i), 'Benutzer'.$i, 'hash'));
        }

        return new UserList($repository);
    }
}
