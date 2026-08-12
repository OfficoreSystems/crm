<?php

declare(strict_types=1);

namespace Crm\Company\Tests\Infrastructure;

use Crm\Company\Domain\Company;
use Crm\Company\Infrastructure\SharedKernel\CompanySubjectResolver;
use Crm\Company\Tests\Double\InMemoryCompanyRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CompanySubjectResolverTest extends TestCase
{
    private InMemoryCompanyRepository $companies;
    private CompanySubjectResolver $resolver;

    protected function setUp(): void
    {
        $this->companies = new InMemoryCompanyRepository();
        $this->resolver = new CompanySubjectResolver($this->companies);
    }

    #[Test]
    public function it_declares_its_type(): void
    {
        self::assertSame('company', $this->resolver->type());
        self::assertNotSame('', $this->resolver->typeLabel());
    }

    #[Test]
    public function it_resolves_ids_to_labelled_and_linkable_subjects(): void
    {
        $this->companies->save($nordwind = Company::create('Nordwind Logistik'));

        $subject = $this->resolver->resolve([(string) $nordwind->id()])[(string) $nordwind->id()];

        self::assertSame('Nordwind Logistik', $subject->label);
        self::assertSame('company', $subject->type);
        self::assertTrue($subject->isLinkable());
    }

    #[Test]
    public function unknown_and_malformed_ids_are_skipped(): void
    {
        $this->companies->save($nordwind = Company::create('Nordwind'));

        $resolved = $this->resolver->resolve([(string) $nordwind->id(), (string) Uuid::v7(), 'kaputt']);

        self::assertCount(1, $resolved);
    }

    #[Test]
    public function it_offers_candidates_for_a_picker(): void
    {
        $this->companies->save(Company::create('Nordwind Logistik'));
        $this->companies->save(Company::create('Atlas Bau'));

        self::assertCount(2, $this->resolver->search(''));
        self::assertCount(1, $this->resolver->search('Atlas'));
        self::assertCount(1, $this->resolver->search('', 1));
    }
}
