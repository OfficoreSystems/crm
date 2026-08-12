<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Subject;

/**
 * Loest polymorphe Verweise auf, indem sie den zustaendigen Resolver findet.
 *
 * Der Kern ist die Buendelung: die Verweise werden nach Typ gruppiert und
 * jeder Resolver genau einmal mit allen IDs seines Typs aufgerufen. Eine
 * Timeline mit fuenfzig Eintraegen ueber drei Module kostet damit drei
 * Aufrufe, nicht fuenfzig.
 *
 * Ist fuer einen Typ kein Resolver registriert - weil das Modul fehlt oder
 * entfernt wurde -, fehlt der Eintrag im Ergebnis. Aufrufer muessen damit
 * ohnehin rechnen: auch ein vorhandener Resolver findet geloeschte
 * Datensaetze nicht mehr.
 */
final class SubjectResolverRegistry
{
    /**
     * @var array<string, SubjectResolverInterface>|null
     */
    private ?array $byType = null;

    /**
     * @param iterable<SubjectResolverInterface> $resolvers
     */
    public function __construct(
        private readonly iterable $resolvers,
    ) {
    }

    /**
     * @param list<SubjectRef> $refs
     *
     * @return array<string, ResolvedSubject> Indiziert nach SubjectRef::key().
     */
    public function resolveAll(array $refs): array
    {
        $idsByType = [];

        foreach ($refs as $ref) {
            $idsByType[$ref->type][$ref->id] = $ref->id;
        }

        $resolved = [];

        foreach ($idsByType as $type => $ids) {
            $resolver = $this->resolverFor($type);

            if (null === $resolver) {
                continue;
            }

            foreach ($resolver->resolve(array_values($ids)) as $id => $subject) {
                $resolved[SubjectRef::keyFor($type, (string) $id)] = $subject;
            }
        }

        return $resolved;
    }

    public function resolve(SubjectRef $ref): ?ResolvedSubject
    {
        return $this->resolveAll([$ref])[$ref->key()] ?? null;
    }

    public function supports(string $type): bool
    {
        return null !== $this->resolverFor($type);
    }

    /**
     * Kandidaten aus allen Typen - oder aus einem, wenn angegeben.
     *
     * @return list<ResolvedSubject>
     */
    public function searchAll(string $query = '', ?string $type = null, int $limitPerType = 10): array
    {
        if (null !== $type) {
            $resolver = $this->resolverFor($type);

            return null === $resolver ? [] : $resolver->search($query, $limitPerType);
        }

        $found = [];

        foreach ($this->indexed() as $resolver) {
            $found = [...$found, ...$resolver->search($query, $limitPerType)];
        }

        return $found;
    }

    /**
     * Alle Typen, die derzeit aufloesbar sind - fuer Filter in der Oberflaeche.
     *
     * @return array<string, string> Typ => Anzeigename, alphabetisch.
     */
    public function supportedTypes(): array
    {
        $labels = [];

        foreach ($this->indexed() as $type => $resolver) {
            $labels[$type] = $resolver->typeLabel();
        }

        asort($labels);

        return $labels;
    }

    private function resolverFor(string $type): ?SubjectResolverInterface
    {
        return $this->indexed()[$type] ?? null;
    }

    /**
     * @return array<string, SubjectResolverInterface>
     */
    private function indexed(): array
    {
        if (null !== $this->byType) {
            return $this->byType;
        }

        $indexed = [];

        foreach ($this->resolvers as $resolver) {
            $type = $resolver->type();

            if (isset($indexed[$type])) {
                // Zwei Module, die denselben Typ beanspruchen, sind ein
                // Installationsfehler - und einer, der sonst nur als
                // gelegentlich falsches Label auffiele.
                throw new \LogicException(sprintf(
                    'Fuer den Subjekt-Typ "%s" sind zwei Resolver registriert: %s und %s.',
                    $type,
                    $indexed[$type]::class,
                    $resolver::class,
                ));
            }

            $indexed[$type] = $resolver;
        }

        return $this->byType = $indexed;
    }
}
