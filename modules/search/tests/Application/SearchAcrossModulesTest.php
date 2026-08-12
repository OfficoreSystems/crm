<?php

declare(strict_types=1);

namespace Crm\Search\Tests\Application;

use Crm\Search\Application\SearchAcrossModules;
use Crm\Search\Domain\SearchHit;
use Crm\Search\Tests\Double\StubResolver;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchAcrossModulesTest extends TestCase
{
    #[Test]
    public function it_finds_across_several_modules(): void
    {
        $hits = $this->search('nordwind');

        self::assertSame(
            ['Nordwind Logistik', 'Anna Berger', 'Deniz Yilmaz'],
            array_map(static fn (SearchHit $h): string => $h->subject->label, $hits),
        );
    }

    #[Test]
    public function the_best_match_comes_first_regardless_of_module(): void
    {
        // Die Firma steht vorn, obwohl der contact-Resolver zuerst gefragt
        // wird - sortiert wird nach Relevanz, nicht nach Reihenfolge der
        // Module.
        $hits = $this->search('nordwind');

        self::assertSame('company', $hits[0]->subject->type);
        self::assertTrue($hits[0]->isStrong());
    }

    #[Test]
    public function ties_are_broken_alphabetically(): void
    {
        // Ohne zweites Kriterium haengt die Reihenfolge davon ab, in welcher
        // Reihenfolge die Module registriert sind - und die aendert sich beim
        // Installieren.
        $registry = new SubjectResolverRegistry([
            new StubResolver('company', 'Firma', [
                $this->subject('company', '1', 'Zeta Bau'),
                $this->subject('company', '2', 'Alpha Bau'),
            ]),
        ]);

        $labels = array_map(
            static fn (SearchHit $h): string => $h->subject->label,
            (new SearchAcrossModules($registry))('bau'),
        );

        self::assertSame(['Alpha Bau', 'Zeta Bau'], $labels);
    }

    #[Test]
    public function an_empty_query_returns_nothing(): void
    {
        // Sonst zeigt die Seite beim Oeffnen die halbe Datenbank.
        self::assertSame([], $this->search(''));
        self::assertSame([], $this->search('   '));
    }

    #[Test]
    public function it_can_be_restricted_to_one_type(): void
    {
        $hits = $this->search('nordwind', 'contact');

        self::assertCount(2, $hits);
        foreach ($hits as $hit) {
            self::assertSame('contact', $hit->subject->type);
        }
    }

    #[Test]
    public function an_unknown_type_yields_nothing_rather_than_everything(): void
    {
        self::assertSame([], $this->search('nordwind', 'invoice'));
    }

    #[Test]
    public function no_single_module_may_flood_the_list(): void
    {
        // Ohne Obergrenze je Modul fuellt eines mit vielen Treffern die ganze
        // Liste, und die eine Firma steht auf Platz 40.
        $many = [];

        for ($i = 1; $i <= 30; ++$i) {
            $many[] = $this->subject('contact', (string) $i, sprintf('Person %02d Bau', $i));
        }

        $registry = new SubjectResolverRegistry([
            new StubResolver('contact', 'Kontakt', $many),
            new StubResolver('company', 'Firma', [$this->subject('company', 'x', 'Alpha Bau')]),
        ]);

        $hits = (new SearchAcrossModules($registry))('bau');
        $types = array_count_values(array_map(static fn (SearchHit $h): string => $h->subject->type, $hits));

        self::assertLessThanOrEqual(8, $types['contact']);
        self::assertArrayHasKey('company', $types, 'Die Firma darf nicht verdraengt werden.');
    }

    #[Test]
    public function it_respects_the_overall_limit(): void
    {
        $registry = new SubjectResolverRegistry([
            new StubResolver('contact', 'Kontakt', [
                $this->subject('contact', '1', 'Anna Bau'),
                $this->subject('contact', '2', 'Bogdan Bau'),
                $this->subject('contact', '3', 'Clara Bau'),
            ]),
        ]);

        self::assertCount(2, (new SearchAcrossModules($registry))('bau', null, 2));
        self::assertCount(1, (new SearchAcrossModules($registry))('bau', null, 0), 'max(1, limit)');
    }

    #[Test]
    public function without_any_module_it_finds_nothing_rather_than_failing(): void
    {
        self::assertSame([], (new SearchAcrossModules(new SubjectResolverRegistry([])))('nordwind'));
    }

    /**
     * @return list<SearchHit>
     */
    private function search(string $query, ?string $type = null): array
    {
        return (new SearchAcrossModules($this->registry()))($query, $type);
    }

    private function registry(): SubjectResolverRegistry
    {
        return new SubjectResolverRegistry([
            new StubResolver('contact', 'Kontakt', [
                $this->subject('contact', 'a', 'Anna Berger', 'anna.berger@nordwind.example'),
                $this->subject('contact', 'd', 'Deniz Yilmaz', 'deniz@nordwind.example'),
                $this->subject('contact', 'e', 'Erik Lindqvist', null),
            ]),
            new StubResolver('company', 'Firma', [
                $this->subject('company', 'x', 'Nordwind Logistik', 'Logistik · Hamburg'),
                $this->subject('company', 'y', 'Atlas Bau', 'Bauwesen · Muenchen'),
            ]),
        ]);
    }

    private function subject(string $type, string $id, string $label, ?string $description = null): ResolvedSubject
    {
        return new ResolvedSubject($type, $id, $label, 'route_'.$type, typeLabel: ucfirst($type), description: $description);
    }
}
