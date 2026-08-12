<?php

declare(strict_types=1);

namespace Crm\Activity\Application;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityRepositoryInterface;
use Crm\Activity\Domain\UnresolvableSubject;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;

/**
 * Use-Case: einen Timeline-Eintrag anlegen.
 *
 * Prueft, ob der Subjekt-Typ ueberhaupt aufloesbar ist. Anders als bei den
 * Firmen- und Kontaktverweisen ist das hier sinnvoll: ein Eintrag an einem
 * Typ, den niemand aufloest, waere in der Timeline dauerhaft namenlos - und
 * der Tippfehler faellt sonst erst Wochen spaeter auf.
 *
 * Geprueft wird der *Typ*, nicht die ID. Ob der Datensatz noch existiert, ist
 * eine Frage von morgen, und ein geloeschter Kontakt soll seine Historie
 * nicht mitreissen.
 */
final readonly class LogActivity
{
    public function __construct(
        private ActivityRepositoryInterface $activities,
        private SubjectResolverRegistry $subjects,
    ) {
    }

    public function __invoke(LogActivityCommand $command): Activity
    {
        if (!$this->subjects->supports($command->subject->type)) {
            throw UnresolvableSubject::ofType($command->subject->type, array_keys($this->subjects->supportedTypes()));
        }

        $activity = Activity::log(
            type: $command->type,
            subject: $command->subject,
            summary: $command->summary,
            body: $command->body,
            authorId: $command->authorId,
            occurredAt: $command->occurredAt,
        );

        $this->activities->save($activity);

        return $activity;
    }
}
