<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Company;

use Crm\SharedKernel\Company\CompanySummary;
use Crm\SharedKernel\Company\NullCompanyFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullCompanyFinderTest extends TestCase
{
    #[Test]
    public function it_finds_nothing(): void
    {
        $finder = new NullCompanyFinder();

        self::assertNull($finder->find('egal'));
        self::assertSame([], $finder->findMany(['a', 'b']));
        self::assertSame([], $finder->findAll());
        self::assertFalse($finder->exists('egal'));
    }

    #[Test]
    public function a_summary_carries_the_fields_other_modules_need(): void
    {
        $summary = new CompanySummary('id-1', 'Nordwind Logistik', 'Logistik', 'Hamburg');

        self::assertSame('id-1', $summary->id);
        self::assertSame('Nordwind Logistik', $summary->name);
        self::assertSame('Logistik', $summary->industry);
        self::assertSame('Hamburg', $summary->city);
    }

    #[Test]
    public function industry_and_city_are_optional(): void
    {
        $summary = new CompanySummary('id-1', 'Nordwind');

        self::assertNull($summary->industry);
        self::assertNull($summary->city);
    }
}
