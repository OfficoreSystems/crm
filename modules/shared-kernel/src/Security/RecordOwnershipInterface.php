<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * Extension point: a module states who owns its records.
 *
 * This lets the voter in the shared kernel decide about access without knowing a
 * single module. It asks: "which module, who is the owner, which team" - and
 * compares that against the permission matrix.
 *
 * Implementations are tagged with `crm.record_ownership` automatically through
 * registerForAutoconfiguration().
 *
 * A module whose data belongs to everyone - master data such as companies -
 * needs no implementation. Such records are then reachable only with ALL rights,
 * and that is exactly right for shared knowledge.
 */
interface RecordOwnershipInterface
{
    /**
     * The module the records belong to - "deal", for instance.
     *
     * The key the permission matrix looks up.
     */
    public function module(): string;

    public function supports(object $record): bool;

    /**
     * Only called once supports() has agreed.
     */
    public function ownershipOf(object $record): RecordOwnership;

    /**
     * The columns the visibility filter can restrict on - or null when this
     * module has no table of its own for it.
     *
     * Null does not mean "unrestricted": the voter still checks every single
     * record. It only means the restriction cannot already happen in SQL.
     */
    public function restrictedColumns(): ?RestrictedColumns;
}
