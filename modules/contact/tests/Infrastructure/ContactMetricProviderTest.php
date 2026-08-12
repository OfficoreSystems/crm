<?php

declare(strict_types=1);

namespace Crm\Contact\Tests\Infrastructure;

use Crm\Contact\Domain\Contact;
use Crm\Contact\Infrastructure\SharedKernel\ContactMetricProvider;
use Crm\Contact\Tests\Double\InMemoryContactRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContactMetricProviderTest extends TestCase
{
    #[Test]
    public function it_reports_the_number_of_contacts(): void
    {
        $repository = new InMemoryContactRepository();
        $repository->save(Contact::create('Anna', 'Berger'));
        $repository->save(Contact::create('Erik', 'Lindqvist'));

        $metrics = iterator_to_array((new ContactMetricProvider($repository))->getMetrics());

        self::assertCount(1, $metrics);
        self::assertSame('contact.total', $metrics[0]->key);
        self::assertSame('2', $metrics[0]->value);
        self::assertTrue($metrics[0]->isLinkable());
    }

    #[Test]
    public function an_empty_database_reports_zero_not_nothing(): void
    {
        // Eine fehlende Kachel saehe aus wie ein kaputtes Modul.
        $metrics = iterator_to_array((new ContactMetricProvider(new InMemoryContactRepository()))->getMetrics());

        self::assertCount(1, $metrics);
        self::assertSame('0', $metrics[0]->value);
    }
}
