<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Tests\Subject;

use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Der polymorphe Extension-Point. Die wichtigste Eigenschaft ist die
 * Buendelung: eine Timeline mit fuenfzig Eintraegen ueber drei Module darf
 * drei Aufrufe kosten, nicht fuenfzig.
 */
final class SubjectResolverRegistryTest extends TestCase
{
    #[Test]
    public function it_resolves_across_several_types(): void
    {
        $registry = new SubjectResolverRegistry([
            new FakeResolver('contact', 'Kontakt', ['a' => 'Anna Berger']),
            new FakeResolver('company', 'Firma', ['x' => 'Nordwind Logistik']),
        ]);

        $resolved = $registry->resolveAll([
            SubjectRef::of('contact', 'a'),
            SubjectRef::of('company', 'x'),
        ]);

        self::assertSame('Anna Berger', $resolved['contact:a']->label);
        self::assertSame('Nordwind Logistik', $resolved['company:x']->label);
    }

    #[Test]
    public function it_calls_each_resolver_exactly_once_regardless_of_row_count(): void
    {
        // Der eigentliche Zweck der Registry. Wuerde je Eintrag aufgeloest,
        // haette man ein N+1 ueber Modulgrenzen.
        $contacts = new FakeResolver('contact', 'Kontakt', ['a' => 'Anna', 'b' => 'Bogdan', 'c' => 'Clara']);
        $companies = new FakeResolver('company', 'Firma', ['x' => 'Nordwind']);
        $registry = new SubjectResolverRegistry([$contacts, $companies]);

        $registry->resolveAll([
            SubjectRef::of('contact', 'a'),
            SubjectRef::of('company', 'x'),
            SubjectRef::of('contact', 'b'),
            SubjectRef::of('contact', 'c'),
            SubjectRef::of('company', 'x'),
        ]);

        self::assertSame(1, $contacts->resolveCalls);
        self::assertSame(1, $companies->resolveCalls);
    }

    #[Test]
    public function it_passes_each_id_only_once(): void
    {
        $contacts = new FakeResolver('contact', 'Kontakt', ['a' => 'Anna']);
        $registry = new SubjectResolverRegistry([$contacts]);

        $registry->resolveAll([
            SubjectRef::of('contact', 'a'),
            SubjectRef::of('contact', 'a'),
            SubjectRef::of('contact', 'a'),
        ]);

        self::assertSame([['a']], $contacts->receivedIds);
    }

    #[Test]
    public function an_unregistered_type_is_skipped_rather_than_failing(): void
    {
        // Der Normalfall nach dem Entfernen eines Moduls. Die Timeline soll
        // dann den Eintrag ohne Namen zeigen, nicht abstuerzen.
        $registry = new SubjectResolverRegistry([new FakeResolver('contact', 'Kontakt', ['a' => 'Anna'])]);

        $resolved = $registry->resolveAll([
            SubjectRef::of('contact', 'a'),
            SubjectRef::of('invoice', 'z'),
        ]);

        self::assertArrayHasKey('contact:a', $resolved);
        self::assertArrayNotHasKey('invoice:z', $resolved);
    }

    #[Test]
    public function an_unknown_id_of_a_known_type_is_skipped(): void
    {
        $registry = new SubjectResolverRegistry([new FakeResolver('contact', 'Kontakt', ['a' => 'Anna'])]);

        self::assertSame([], $registry->resolveAll([SubjectRef::of('contact', 'geloescht')]));
    }

    #[Test]
    public function resolving_a_single_ref_returns_null_when_unknown(): void
    {
        $registry = new SubjectResolverRegistry([new FakeResolver('contact', 'Kontakt', ['a' => 'Anna'])]);

        self::assertSame('Anna', $registry->resolve(SubjectRef::of('contact', 'a'))?->label);
        self::assertNull($registry->resolve(SubjectRef::of('contact', 'weg')));
        self::assertNull($registry->resolve(SubjectRef::of('invoice', 'a')));
    }

    #[Test]
    public function it_reports_which_types_are_available(): void
    {
        $registry = new SubjectResolverRegistry([
            new FakeResolver('deal', 'Verkaufschance', []),
            new FakeResolver('contact', 'Kontakt', []),
        ]);

        self::assertTrue($registry->supports('contact'));
        self::assertFalse($registry->supports('invoice'));
        // Alphabetisch nach Anzeigename, damit die Auswahl stabil ist.
        self::assertSame(['contact' => 'Kontakt', 'deal' => 'Verkaufschance'], $registry->supportedTypes());
    }

    #[Test]
    public function without_any_resolver_it_stays_empty_instead_of_failing(): void
    {
        $registry = new SubjectResolverRegistry([]);

        self::assertSame([], $registry->supportedTypes());
        self::assertSame([], $registry->resolveAll([SubjectRef::of('contact', 'a')]));
        self::assertSame([], $registry->searchAll());
        self::assertFalse($registry->supports('contact'));
    }

    #[Test]
    public function two_resolvers_for_the_same_type_are_refused(): void
    {
        // Ein Installationsfehler, der sonst nur als gelegentlich falsches
        // Label auffiele.
        $registry = new SubjectResolverRegistry([
            new FakeResolver('contact', 'Kontakt', []),
            new FakeResolver('contact', 'Ansprechpartner', []),
        ]);

        $this->expectException(\LogicException::class);

        $registry->supportedTypes();
    }

    #[Test]
    public function it_searches_across_all_types(): void
    {
        $registry = new SubjectResolverRegistry([
            new FakeResolver('contact', 'Kontakt', ['a' => 'Anna Berger']),
            new FakeResolver('company', 'Firma', ['x' => 'Nordwind Logistik']),
        ]);

        self::assertCount(2, $registry->searchAll());
        self::assertCount(1, $registry->searchAll('Anna'));
        self::assertCount(1, $registry->searchAll('', 'contact'));
        self::assertSame([], $registry->searchAll('', 'invoice'));
    }
}

/**
 * Zaehlt Aufrufe und merkt sich die uebergebenen IDs.
 */
final class FakeResolver implements SubjectResolverInterface
{
    public int $resolveCalls = 0;

    /**
     * @var list<list<string>>
     */
    public array $receivedIds = [];

    /**
     * @param array<string, string> $labelsById
     */
    public function __construct(
        private readonly string $type,
        private readonly string $typeLabel,
        private readonly array $labelsById,
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function typeLabel(): string
    {
        return $this->typeLabel;
    }

    public function resolve(array $ids): array
    {
        ++$this->resolveCalls;
        $this->receivedIds[] = $ids;

        $resolved = [];

        foreach ($ids as $id) {
            if (isset($this->labelsById[$id])) {
                $resolved[$id] = new ResolvedSubject($this->type, $id, $this->labelsById[$id], typeLabel: $this->typeLabel);
            }
        }

        return $resolved;
    }

    public function search(string $query, int $limit = 10): array
    {
        $needle = mb_strtolower(trim($query));
        $found = [];

        foreach ($this->labelsById as $id => $label) {
            if ('' === $needle || str_contains(mb_strtolower($label), $needle)) {
                $found[] = new ResolvedSubject($this->type, (string) $id, $label, typeLabel: $this->typeLabel);
            }
        }

        return \array_slice($found, 0, max(1, $limit));
    }
}
