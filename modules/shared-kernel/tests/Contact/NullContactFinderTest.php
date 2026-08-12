<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Contact;

use Crm\SharedKernel\Contact\ContactSummary;
use Crm\SharedKernel\Contact\NullContactFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullContactFinderTest extends TestCase
{
    #[Test]
    public function it_finds_nothing(): void
    {
        $finder = new NullContactFinder();

        self::assertNull($finder->find('egal'));
        self::assertSame([], $finder->findMany(['a', 'b']));
        self::assertSame([], $finder->searchByName('Anna'));
        self::assertFalse($finder->exists('egal'));
    }

    #[Test]
    public function a_summary_carries_the_fields_other_modules_need(): void
    {
        $summary = new ContactSummary('id-1', 'Anna Berger', 'anna@example.test', 'company-1');

        self::assertSame('id-1', $summary->id);
        self::assertSame('Anna Berger', $summary->fullName);
        self::assertSame('anna@example.test', $summary->email);
        self::assertSame('company-1', $summary->companyId);
    }

    #[Test]
    public function email_and_company_are_optional(): void
    {
        $summary = new ContactSummary('id-1', 'Anna Berger');

        self::assertNull($summary->email);
        self::assertNull($summary->companyId);
    }
}
