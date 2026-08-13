<?php

declare(strict_types=1);

namespace Crm\Activity\Domain;

enum ActivityType: string
{
    case NOTE = 'note';
    case CALL = 'call';
    case MEETING = 'meeting';
    case TASK = 'task';

    public function label(): string
    {
        return match ($this) {
            self::NOTE => 'activity.type.note',
            self::CALL => 'activity.type.call',
            self::MEETING => 'activity.type.meeting',
            self::TASK => 'activity.type.task',
        };
    }

    /**
     * Nur Aufgaben lassen sich abhaken. Eine erledigte Notiz ergibt keinen
     * Sinn, und ohne diese Unterscheidung landet frueher oder spaeter ein
     * Haken an einem Anruf.
     */
    public function isCompletable(): bool
    {
        return self::TASK === $this;
    }
}
