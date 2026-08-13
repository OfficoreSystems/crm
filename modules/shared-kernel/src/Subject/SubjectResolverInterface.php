<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Subject;

/**
 * Extension point: a module makes its records referenceable as a subject.
 *
 * This lets other modules - activity today, document and email later - attach
 * something to a contact, a company or a deal without knowing any of those
 * modules.
 *
 * Implementations are tagged with `crm.subject_resolver` automatically through
 * registerForAutoconfiguration().
 *
 * On the signature of resolve(): it takes a *list* of IDs, not a single one. A
 * timeline shows dozens of entries, and one call per entry would be an N+1
 * across a module boundary. The registry therefore groups by type and calls each
 * resolver exactly once.
 */
interface SubjectResolverInterface
{
    /**
     * The type this module resolves - "contact", for instance.
     */
    public function type(): string;

    /**
     * Display name of the type - a translation key, not a finished text.
     */
    public function typeLabel(): string;

    /**
     * @param list<string> $ids
     *
     * @return array<string, ResolvedSubject> Indexed by ID. Unknown IDs are
     *                                        missing from the result.
     */
    public function resolve(array $ids): array;

    /**
     * Candidates for a selection.
     *
     * Resolving alone is not enough: whoever wants to attach something to a
     * subject - an activity, later a document or an email - has to be able to
     * pick one first. Without this method every such module would have to know
     * the concrete finders of contact, company and deal, and the extension point
     * would only be half of one.
     *
     * An empty query returns the first entries, not none - that is what a select
     * field is expected to do when it opens.
     *
     * @return list<ResolvedSubject>
     */
    public function search(string $query, int $limit = 10): array;
}
