<?php

declare(strict_types=1);

namespace Crm\SharedKernel\Security;

/**
 * The four actions rights exist for.
 *
 * Deliberately exactly four and not freely extensible: as soon as every module
 * brings its own actions ("export", "assign", "close"), the permission matrix
 * stops being surveyable and nobody can say what a role actually may do.
 * Special cases belong in a role, not in a new action.
 */
enum Action: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case EDIT = 'edit';
    case DELETE = 'delete';

    /**
     * A translation key, not a finished text.
     */
    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'security.action.view',
            self::CREATE => 'security.action.create',
            self::EDIT => 'security.action.edit',
            self::DELETE => 'security.action.delete',
        };
    }

    /**
     * Actions that need a concrete record.
     *
     * "create" is the exception: there is nothing yet whose owner could be
     * checked.
     */
    public function needsRecord(): bool
    {
        return self::CREATE !== $this;
    }
}
