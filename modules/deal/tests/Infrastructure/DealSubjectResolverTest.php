<?php

declare(strict_types=1);

namespace Crm\Deal\Tests\Infrastructure;

use Crm\Deal\Domain\Deal;
use Crm\Deal\Infrastructure\SharedKernel\DealSubjectResolver;
use Crm\Deal\Tests\Double\InMemoryDealRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DealSubjectResolverTest extends TestCase
{
    private InMemoryDealRepository $deals;
    private DealSubjectResolver $resolver;

    protected function setUp(): void
    {
        $this->deals = new InMemoryDealRepository();
        $this->resolver = new DealSubjectResolver($this->deals);
    }

    #[Test]
    public function it_declares_its_type(): void
    {
        self::assertSame('deal', $this->resolver->type());
        self::assertNotSame('', $this->resolver->typeLabel());
    }

    #[Test]
    public function it_resolves_ids_to_labelled_and_linkable_subjects(): void
    {
        $this->deals->save($deal = Deal::create('Rahmenvertrag Seefracht'));

        $subject = $this->resolver->resolve([(string) $deal->id()])[(string) $deal->id()];

        self::assertSame('Rahmenvertrag Seefracht', $subject->label);
        self::assertSame('deal', $subject->type);
        self::assertTrue($subject->isLinkable());
    }

    #[Test]
    public function unknown_and_malformed_ids_are_skipped(): void
    {
        $this->deals->save($deal = Deal::create('Rahmenvertrag'));

        $resolved = $this->resolver->resolve([(string) $deal->id(), (string) Uuid::v7(), 'kaputt']);

        self::assertCount(1, $resolved);
    }

    #[Test]
    public function it_offers_candidates_for_a_picker(): void
    {
        $this->deals->save(Deal::create('Rahmenvertrag Seefracht'));
        $this->deals->save(Deal::create('Neubau Buerokomplex'));

        self::assertCount(2, $this->resolver->search(''));
        self::assertCount(1, $this->resolver->search('Neubau'));
        self::assertCount(1, $this->resolver->search('', 1));
    }
}
