<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\UI;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use Crm\Contact\UI\Component\ContactList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContactListTest extends TestCase
{
    #[Test]
    public function it_lists_everything_without_a_query(): void
    {
        $component = $this->componentWith(3);

        self::assertCount(3, $component->getContacts());
        self::assertSame(3, $component->getTotal());
    }

    #[Test]
    public function it_narrows_the_list_down_to_the_query(): void
    {
        $component = $this->componentWith(3);
        $component->query = 'Nachname1';

        self::assertCount(1, $component->getContacts());
        // Der Gesamtwert bleibt - das Template zeigt "1 von 3".
        self::assertSame(3, $component->getTotal());
    }

    #[Test]
    public function an_empty_query_does_not_count_as_filtered(): void
    {
        $component = $this->componentWith(1);

        self::assertFalse($component->isFiltered());
    }

    #[Test]
    public function a_whitespace_query_does_not_count_as_filtered(): void
    {
        // Sonst zeigt das Template "Kein Treffer fuer '   '" statt
        // "Noch keine Kontakte angelegt".
        $component = $this->componentWith(1);
        $component->query = '   ';

        self::assertFalse($component->isFiltered());
    }

    #[Test]
    public function a_real_query_counts_as_filtered(): void
    {
        $component = $this->componentWith(1);
        $component->query = 'Berger';

        self::assertTrue($component->isFiltered());
    }

    #[Test]
    public function a_short_list_is_not_truncated(): void
    {
        self::assertFalse($this->componentWith(5)->isTruncated());
    }

    #[Test]
    public function a_full_page_is_reported_as_truncated(): void
    {
        // Bei genau LIMIT Treffern kann niemand wissen, ob noch mehr kommt -
        // das Template weist deshalb darauf hin.
        self::assertTrue($this->componentWith(50)->isTruncated());
    }

    private function componentWith(int $count): ContactList
    {
        $repository = new InMemoryContactRepository();

        for ($i = 1; $i <= $count; ++$i) {
            $repository->save(Contact::create('Vorname'.$i, 'Nachname'.$i));
        }

        return new ContactList($repository);
    }
}
