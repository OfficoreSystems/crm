<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Subject;

/**
 * Resolves polymorphic references by finding the responsible resolver.
 *
 * The core of it is the bundling: references are grouped by type and each
 * resolver is called exactly once with every ID of its type. A timeline with
 * fifty entries across three modules therefore costs three calls, not fifty.
 *
 * If no resolver is registered for a type - because the module is absent or was
 * removed - the entry is missing from the result. Callers have to expect that
 * anyway: even an existing resolver no longer finds deleted records.
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
     * @return array<string, ResolvedSubject> Indexed by SubjectRef::key().
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
     * Candidates from every type - or from one, when given.
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
     * Every type that is currently resolvable - for filters in the interface.
     *
     * @return array<string, string> type => display name, alphabetically.
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
                // Two modules claiming the same type are an installation
                // error - and one that would otherwise only show up as an
                // occasionally wrong label.
                throw new \LogicException(sprintf(
                    'Two resolvers are registered for the subject type "%s": %s and %s.',
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
