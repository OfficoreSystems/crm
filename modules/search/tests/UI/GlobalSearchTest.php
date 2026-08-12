<?php

declare(strict_types=1);

namespace Crm\Search\Tests\UI;

use Crm\Search\Application\SearchAcrossModules;
use Crm\Search\Tests\Double\StubResolver;
use Crm\Search\UI\Component\GlobalSearch;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GlobalSearchTest extends TestCase
{
    #[Test]
    public function without_a_query_it_shows_nothing(): void
    {
        $component = $this->component();

        self::assertFalse($component->hasQuery());
        self::assertSame([], $component->getHits());
    }

    #[Test]
    public function whitespace_does_not_count_as_a_query(): void
    {
        $component = $this->component();
        $component->query = '   ';

        self::assertFalse($component->hasQuery());
    }

    #[Test]
    public function it_groups_hits_by_type(): void
    {
        $component = $this->component();
        $component->query = 'nordwind';

        $grouped = $component->getGroupedHits();

        self::assertArrayHasKey('company', $grouped);
        self::assertArrayHasKey('contact', $grouped);
        self::assertCount(1, $grouped['company']);
        self::assertCount(2, $grouped['contact']);
    }

    #[Test]
    public function the_group_order_follows_the_best_hit(): void
    {
        // Die Firma hat den staerksten Treffer, also steht ihre Gruppe vorn.
        $component = $this->component();
        $component->query = 'nordwind';

        self::assertSame(['company', 'contact'], array_keys($component->getGroupedHits()));
    }

    #[Test]
    public function it_can_be_restricted_to_one_type(): void
    {
        $component = $this->component();
        $component->query = 'nordwind';
        $component->type = 'contact';

        self::assertSame(['contact'], array_keys($component->getGroupedHits()));
    }

    #[Test]
    public function it_offers_exactly_the_registered_types(): void
    {
        // Waechst und schrumpft mit den installierten Modulen.
        self::assertSame(
            ['company' => 'Firma', 'contact' => 'Kontakt'],
            $this->component()->getTypes(),
        );
    }

    #[Test]
    public function it_falls_back_to_the_raw_type_when_no_label_is_known(): void
    {
        self::assertSame('Kontakt', $this->component()->labelForType('contact'));
        self::assertSame('invoice', $this->component()->labelForType('invoice'));
    }

    #[Test]
    public function it_counts_the_strong_hits(): void
    {
        $component = $this->component();
        $component->query = 'nordwind';

        // Nur die Firma trifft im Label, die Kontakte nur ueber ihre Adresse.
        self::assertSame(1, $component->getStrongHits());
    }

    #[Test]
    public function without_any_module_it_stays_empty_instead_of_failing(): void
    {
        $registry = new SubjectResolverRegistry([]);
        $component = new GlobalSearch(new SearchAcrossModules($registry), $registry);
        $component->query = 'nordwind';

        self::assertSame([], $component->getTypes());
        self::assertSame([], $component->getHits());
        self::assertSame([], $component->getGroupedHits());
    }

    private function component(): GlobalSearch
    {
        $registry = new SubjectResolverRegistry([
            new StubResolver('contact', 'Kontakt', [
                new ResolvedSubject('contact', 'a', 'Anna Berger', 'contact_index', typeLabel: 'Kontakt', description: 'anna@nordwind.example'),
                new ResolvedSubject('contact', 'd', 'Deniz Yilmaz', 'contact_index', typeLabel: 'Kontakt', description: 'deniz@nordwind.example'),
            ]),
            new StubResolver('company', 'Firma', [
                new ResolvedSubject('company', 'x', 'Nordwind Logistik', 'company_index', typeLabel: 'Firma', description: 'Logistik · Hamburg'),
            ]),
        ]);

        return new GlobalSearch(new SearchAcrossModules($registry), $registry);
    }
}
