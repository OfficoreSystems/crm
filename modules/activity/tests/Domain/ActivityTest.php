<?php

declare(strict_types=1);

namespace Crm\Activity\Tests\Domain;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityType;
use Crm\SharedKernel\Subject\SubjectRef;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class ActivityTest extends TestCase
{
    #[Test]
    public function it_keeps_its_polymorphic_subject(): void
    {
        $activity = $this->note(SubjectRef::of('company', 'x'));

        self::assertTrue(SubjectRef::of('company', 'x')->equals($activity->subject()));
        self::assertInstanceOf(UuidV7::class, $activity->id());
    }

    #[Test]
    public function it_trims_the_summary(): void
    {
        self::assertSame('Gespraech', $this->note(summary: '  Gespraech  ')->summary());
    }

    #[Test]
    public function it_rejects_a_blank_summary(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->note(summary: '   ');
    }

    #[Test]
    public function a_blank_body_becomes_null(): void
    {
        self::assertNull(Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'Titel', '  ')->body());
    }

    #[Test]
    public function it_defaults_the_moment_to_now(): void
    {
        $created = new \DateTimeImmutable('2026-03-01 09:15:00');

        $activity = Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'Titel', createdAt: $created);

        self::assertSame($created, $activity->occurredAt());
    }

    #[Test]
    public function only_tasks_can_be_completed(): void
    {
        // Eine erledigte Notiz ergibt keinen Sinn. Ohne diese Unterscheidung
        // landet frueher oder spaeter ein Haken an einem Anruf.
        $task = Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'Angebot nachfassen');
        $task->complete();

        self::assertTrue($task->isCompleted());

        $this->expectException(\DomainException::class);

        $this->note()->complete();
    }

    #[Test]
    public function a_completed_task_can_be_reopened(): void
    {
        $task = Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'Angebot nachfassen');
        $task->complete();

        $task->reopen();

        self::assertFalse($task->isCompleted());
        self::assertTrue($task->isOpenTask());
    }

    #[Test]
    public function only_open_tasks_can_be_overdue(): void
    {
        $now = new \DateTimeImmutable('2026-03-10');
        $past = new \DateTimeImmutable('2026-03-01');

        $openTask = Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'faellig', occurredAt: $past);
        $doneTask = Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'erledigt', occurredAt: $past);
        $doneTask->complete();
        $oldNote = Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'alt', occurredAt: $past);

        self::assertTrue($openTask->isOverdue($now));
        self::assertFalse($doneTask->isOverdue($now), 'Erledigtes ist nie ueberfaellig');
        self::assertFalse($oldNote->isOverdue($now), 'Eine alte Notiz ist nicht ueberfaellig');
    }

    #[Test]
    public function a_future_task_is_not_overdue(): void
    {
        $task = Activity::log(
            ActivityType::TASK,
            SubjectRef::of('contact', 'a'),
            'spaeter',
            occurredAt: new \DateTimeImmutable('2026-04-01'),
        );

        self::assertFalse($task->isOverdue(new \DateTimeImmutable('2026-03-10')));
    }

    #[Test]
    public function it_can_be_rewritten(): void
    {
        $activity = $this->note();

        $activity->rewrite('  Neuer Titel ', ' Neuer Text ');

        self::assertSame('Neuer Titel', $activity->summary());
        self::assertSame('Neuer Text', $activity->body());
    }

    #[Test]
    public function it_can_be_moved_to_another_subject(): void
    {
        // Die Domain prueft nicht, ob es das Ziel gibt - dafuer muesste sie
        // die Resolver kennen. Das erledigt der Use-Case.
        $activity = $this->note(SubjectRef::of('contact', 'a'));

        $activity->moveTo(SubjectRef::of('deal', 'z'));

        self::assertTrue(SubjectRef::of('deal', 'z')->equals($activity->subject()));
    }

    #[Test]
    public function it_can_carry_an_author(): void
    {
        $author = Uuid::v7();

        $activity = Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'Titel', authorId: $author);

        self::assertTrue($author->equals($activity->authorId()));
        self::assertNull($this->note()->authorId());
    }

    #[Test]
    public function every_type_has_a_label_and_knows_whether_it_is_completable(): void
    {
        foreach (ActivityType::cases() as $type) {
            self::assertNotSame('', $type->label());
        }

        self::assertTrue(ActivityType::TASK->isCompletable());
        self::assertFalse(ActivityType::NOTE->isCompletable());
        self::assertFalse(ActivityType::CALL->isCompletable());
        self::assertFalse(ActivityType::MEETING->isCompletable());
    }

    private function note(?SubjectRef $subject = null, string $summary = 'Titel'): Activity
    {
        return Activity::log(ActivityType::NOTE, $subject ?? SubjectRef::of('contact', 'a'), $summary);
    }
}
