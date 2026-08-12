<?php

declare(strict_types=1);

namespace Crm\Activity\Tests\Application;

use Crm\Activity\Application\CompleteTask;
use Crm\Activity\Application\LogActivity;
use Crm\Activity\Application\LogActivityCommand;
use Crm\Activity\Domain\ActivityType;
use Crm\Activity\Domain\UnresolvableSubject;
use Crm\Activity\Tests\Double\InMemoryActivityRepository;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\SharedKernel\Subject\SubjectResolverInterface;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LogActivityTest extends TestCase
{
    #[Test]
    public function it_persists_the_entry(): void
    {
        $activities = new InMemoryActivityRepository();

        $activity = (new LogActivity($activities, $this->registry()))(new LogActivityCommand(
            ActivityType::CALL,
            SubjectRef::of('contact', 'a'),
            'Rueckruf vereinbart',
        ));

        self::assertSame(1, $activities->countAll());
        self::assertSame($activity, $activities->find($activity->id()));
    }

    #[Test]
    public function it_rejects_a_subject_type_nobody_resolves(): void
    {
        // Ein Eintrag an einem unbekannten Typ waere in der Timeline dauerhaft
        // namenlos, und der Tippfehler faellt sonst erst Wochen spaeter auf.
        $activities = new InMemoryActivityRepository();

        try {
            (new LogActivity($activities, $this->registry()))(new LogActivityCommand(
                ActivityType::NOTE,
                SubjectRef::of('invoice', 'z'),
                'Notiz',
            ));
            self::fail('Ein unbekannter Subjekt-Typ haette abgelehnt werden muessen.');
        } catch (UnresolvableSubject $e) {
            self::assertStringContainsString('invoice', $e->getMessage());
            self::assertStringContainsString('contact', $e->getMessage(), 'Die Meldung nennt die verfuegbaren Typen.');
            self::assertSame(0, $activities->countAll());
        }
    }

    #[Test]
    public function it_checks_the_type_but_not_the_id(): void
    {
        // Ob der Datensatz noch existiert, ist eine Frage von morgen. Ein
        // geloeschter Kontakt soll seine Historie nicht mitreissen.
        $activities = new InMemoryActivityRepository();

        (new LogActivity($activities, $this->registry()))(new LogActivityCommand(
            ActivityType::NOTE,
            SubjectRef::of('contact', 'laengst-geloescht'),
            'Notiz',
        ));

        self::assertSame(1, $activities->countAll());
    }

    #[Test]
    public function without_any_resolver_nothing_can_be_logged(): void
    {
        $activities = new InMemoryActivityRepository();

        $this->expectException(UnresolvableSubject::class);

        (new LogActivity($activities, new SubjectResolverRegistry([])))(new LogActivityCommand(
            ActivityType::NOTE,
            SubjectRef::of('contact', 'a'),
            'Notiz',
        ));
    }

    #[Test]
    public function completing_a_task_persists_it(): void
    {
        $activities = new InMemoryActivityRepository();
        $task = (new LogActivity($activities, $this->registry()))(new LogActivityCommand(
            ActivityType::TASK,
            SubjectRef::of('contact', 'a'),
            'Angebot nachfassen',
        ));

        (new CompleteTask($activities))($task, new \DateTimeImmutable('2026-03-01'));

        self::assertTrue($activities->find($task->id())?->isCompleted());
        self::assertSame(0, $activities->countOpenTasks());
    }

    #[Test]
    public function completing_something_that_is_not_a_task_is_refused(): void
    {
        $activities = new InMemoryActivityRepository();
        $note = (new LogActivity($activities, $this->registry()))(new LogActivityCommand(
            ActivityType::NOTE,
            SubjectRef::of('contact', 'a'),
            'Notiz',
        ));

        $this->expectException(\DomainException::class);

        (new CompleteTask($activities))($note);
    }

    private function registry(): SubjectResolverRegistry
    {
        return new SubjectResolverRegistry([
            new class implements SubjectResolverInterface {
                public function type(): string
                {
                    return 'contact';
                }

                public function typeLabel(): string
                {
                    return 'Kontakt';
                }

                public function resolve(array $ids): array
                {
                    return [];
                }

                public function search(string $query, int $limit = 10): array
                {
                    return [new ResolvedSubject('contact', 'a', 'Anna Berger', typeLabel: 'Kontakt')];
                }
            },
        ]);
    }
}
