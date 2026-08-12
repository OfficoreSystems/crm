<?php

declare(strict_types=1);

namespace Crm\Activity\Tests\Infrastructure;

use Crm\Activity\Domain\Activity;
use Crm\Activity\Domain\ActivityRepositoryInterface;
use Crm\Activity\Domain\ActivityType;
use Crm\SharedKernel\Subject\SubjectRef;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineActivityRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ActivityRepositoryInterface $activities;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->activities = $container->get(ActivityRepositoryInterface::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();

        parent::tearDown();
    }

    #[Test]
    public function the_polymorphic_subject_survives_a_round_trip(): void
    {
        $activity = Activity::log(ActivityType::MEETING, SubjectRef::of('deal', 'abc-123'), 'Termin');
        $this->activities->save($activity);

        $this->entityManager->clear();
        $found = $this->activities->find($activity->id());

        self::assertNotNull($found);
        self::assertTrue(SubjectRef::of('deal', 'abc-123')->equals($found->subject()));
        self::assertSame(ActivityType::MEETING, $found->type());
    }

    #[Test]
    public function it_finds_the_timeline_of_one_subject(): void
    {
        $this->givenActivities();

        self::assertCount(2, $this->activities->findForSubject(SubjectRef::of('contact', 'a')));
        self::assertSame(2, $this->activities->countForSubject(SubjectRef::of('contact', 'a')));
        self::assertSame(0, $this->activities->countForSubject(SubjectRef::of('contact', 'unbekannt')));
    }

    #[Test]
    public function the_same_id_under_a_different_type_is_a_different_subject(): void
    {
        // Zwei Module koennen dieselbe ID vergeben. Erst Typ und ID zusammen
        // sind eindeutig - deshalb der gemeinsame Index ueber beide Spalten.
        $this->activities->save(Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'gleich'), 'Kontakt'));
        $this->activities->save(Activity::log(ActivityType::NOTE, SubjectRef::of('company', 'gleich'), 'Firma'));

        self::assertCount(1, $this->activities->findForSubject(SubjectRef::of('contact', 'gleich')));
        self::assertCount(1, $this->activities->findForSubject(SubjectRef::of('company', 'gleich')));
    }

    #[Test]
    public function the_recent_list_is_newest_first(): void
    {
        $this->givenActivities();

        $summaries = array_map(
            static fn (Activity $a): string => $a->summary(),
            $this->activities->findRecent(),
        );

        self::assertSame('neuester', $summaries[0]);
    }

    #[Test]
    public function the_recent_list_can_be_filtered(): void
    {
        $this->givenActivities();

        self::assertCount(2, $this->activities->findRecent('contact'));
        self::assertCount(1, $this->activities->findRecent('company'));
        self::assertCount(1, $this->activities->findRecent(null, ActivityType::TASK));
        self::assertCount(1, $this->activities->findRecent('contact', ActivityType::TASK));
        self::assertCount(0, $this->activities->findRecent('company', ActivityType::TASK));
    }

    #[Test]
    public function it_finds_overdue_tasks_oldest_first(): void
    {
        $now = new \DateTimeImmutable('2026-03-10');
        $this->activities->save(Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'aelter', occurredAt: new \DateTimeImmutable('2026-03-01')));
        $this->activities->save(Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'juenger', occurredAt: new \DateTimeImmutable('2026-03-05')));
        $this->activities->save(Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'zukunft', occurredAt: new \DateTimeImmutable('2026-04-01')));
        $this->activities->save(Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'alte notiz', occurredAt: new \DateTimeImmutable('2026-01-01')));

        $overdue = $this->activities->findOverdueTasks($now);

        self::assertCount(2, $overdue, 'Nur offene Aufgaben in der Vergangenheit');
        self::assertSame('aelter', $overdue[0]->summary(), 'Was am laengsten liegt, draengt am meisten');
    }

    #[Test]
    public function a_completed_task_is_no_longer_overdue_or_open(): void
    {
        $task = Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'faellig', occurredAt: new \DateTimeImmutable('2026-03-01'));
        $this->activities->save($task);
        self::assertSame(1, $this->activities->countOpenTasks());

        $task->complete();
        $this->activities->save($task);

        self::assertSame(0, $this->activities->countOpenTasks());
        self::assertCount(0, $this->activities->findOverdueTasks(new \DateTimeImmutable('2026-03-10')));
    }

    #[Test]
    public function it_removes_an_activity(): void
    {
        $activity = Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'Notiz');
        $this->activities->save($activity);

        $this->activities->remove($activity);

        self::assertSame(0, $this->activities->countAll());
        self::assertNull($this->activities->find($activity->id()));
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        self::assertNull($this->activities->find(Uuid::v7()));
    }

    private function givenActivities(): void
    {
        $this->activities->save(Activity::log(ActivityType::NOTE, SubjectRef::of('contact', 'a'), 'aeltester', occurredAt: new \DateTimeImmutable('2026-03-01')));
        $this->activities->save(Activity::log(ActivityType::TASK, SubjectRef::of('contact', 'a'), 'mittlerer', occurredAt: new \DateTimeImmutable('2026-03-05')));
        $this->activities->save(Activity::log(ActivityType::CALL, SubjectRef::of('company', 'x'), 'neuester', occurredAt: new \DateTimeImmutable('2026-03-09')));
    }
}
