<?php

declare(strict_types=1);

namespace Crm\Calendar\UI\Controller;

use Crm\Calendar\Application\ScheduleAppointment;
use Crm\Calendar\Application\ScheduleAppointmentCommand;
use Crm\Calendar\Application\SubscribeToCalendar;
use Crm\Calendar\Domain\Appointment;
use Crm\Calendar\Domain\AppointmentRepositoryInterface;
use Crm\Calendar\Domain\TimeSpan;
use Crm\Calendar\Domain\InvalidAppointment;
use Crm\Calendar\Domain\UnresolvableSubject;
use Crm\SharedKernel\Security\ActorInterface;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/calendar', name: 'calendar_')]
final class CalendarController extends AbstractController
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly SubjectResolverRegistry $subjects,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('calendar.view')]
    public function index(): Response
    {
        $upcoming = $this->appointments->findUpcoming($this->now(), 100);

        return $this->render('@CalendarModule/calendar/index.html.twig', [
            'appointments' => $upcoming,
            'labels' => $this->resolveLabels($upcoming),
            'types' => $this->subjects->supportedTypes(),
        ]);
    }

    #[Route('/new', name: 'create', methods: ['POST'])]
    #[IsGranted('calendar.create')]
    public function create(Request $request, ScheduleAppointment $schedule): Response
    {
        $actor = $this->getUser();

        try {
            ($schedule)(new ScheduleAppointmentCommand(
                title: (string) $request->request->get('title'),
                when: $this->readTimeSpan($request),
                description: $request->request->get('description') ? (string) $request->request->get('description') : null,
                location: $request->request->get('location') ? (string) $request->request->get('location') : null,
                subject: $this->readSubject($request),
                ownerId: $this->actorId($actor),
                ownerTeamId: $this->actorTeamId($actor),
            ));

            $this->addFlash('success', 'calendar.form.success');
        } catch (UnresolvableSubject|InvalidAppointment $e) {
            // Der uebersetzbare Teil, nicht getMessage() - die Meldung der
            // Ausnahme ist fuers Log und bleibt englisch.
            $this->addFlash('error', $e->translatable);
        }

        return $this->redirectToRoute('calendar_index');
    }

    /**
     * Die Seite mit der Abonnement-URL.
     *
     * Das Token steht nur unmittelbar nach dem Erzeugen im Klartext zur
     * Verfuegung - danach nie wieder. Deshalb bekommt der Benutzer hier
     * entweder die volle URL oder den Hinweis, dass er eine neue erzeugen
     * muss.
     */
    #[Route('/subscription', name: 'subscription', methods: ['GET', 'POST'])]
    #[IsGranted('calendar.view')]
    public function subscription(Request $request, SubscribeToCalendar $subscribe): Response
    {
        $actor = $this->getUser();
        $userId = $this->actorId($actor);

        if (null === $userId) {
            throw $this->createAccessDeniedException('Ohne Benutzerkonto gibt es kein persoenliches Abonnement.');
        }

        $token = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('calendar_feed_'.$userId, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'calendar.subscription.invalid_token');
            } else {
                $token = $subscribe->regenerate($userId);
                $this->addFlash('success', 'calendar.subscription.regenerated');
            }
        } else {
            [, $token] = ($subscribe)($userId);
        }

        return $this->render('@CalendarModule/calendar/subscription.html.twig', [
            'url' => null === $token ? null : $this->generateUrl(
                'calendar_feed',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            'userId' => $userId,
        ]);
    }

    private function readTimeSpan(Request $request): TimeSpan
    {
        $allDay = (bool) $request->request->get('all_day');
        $start = $this->readMoment((string) $request->request->get('start'));

        if ($allDay) {
            return TimeSpan::allDay($start);
        }

        return TimeSpan::of($start, $this->readMoment((string) $request->request->get('end')));
    }

    /**
     * Liest eine Eingabe aus einem datetime-local-Feld.
     *
     * Das Feld liefert *Ortszeit ohne Zeitzone* - "2026-08-13T14:00". Welche
     * Ortszeit gemeint ist, weiss der Browser, aber er sagt es nicht. Deshalb
     * die feste Annahme unten; sie steht an genau dieser Stelle, damit sie
     * beim Einbau einer Zeitzoneneinstellung je Benutzer auffindbar ist.
     */
    private function readMoment(string $raw): \DateTimeImmutable
    {
        $raw = trim($raw);

        if ('' === $raw) {
            throw InvalidAppointment::withoutStart();
        }

        try {
            return new \DateTimeImmutable($raw, new \DateTimeZone(self::INPUT_TIMEZONE));
        } catch (\Exception) {
            throw InvalidAppointment::unparsable($raw);
        }
    }

    /**
     * Die Zeitzone, in der Eingaben gemeint sind.
     *
     * Vorerst fest. Eine Einstellung je Benutzer waere der naechste Schritt -
     * die Speicherung ist darauf vorbereitet, weil intern ohnehin alles UTC
     * ist.
     */
    private const INPUT_TIMEZONE = 'Europe/Berlin';

    private function readSubject(Request $request): ?SubjectRef
    {
        $type = trim((string) $request->request->get('subject_type'));
        $id = trim((string) $request->request->get('subject_id'));

        return '' === $type || '' === $id ? null : new SubjectRef($type, $id);
    }

    /**
     * @param list<Appointment> $appointments
     *
     * @return array<string, ResolvedSubject>
     */
    private function resolveLabels(array $appointments): array
    {
        $refs = [];

        foreach ($appointments as $appointment) {
            $subject = $appointment->subject();

            if (null !== $subject) {
                $refs[] = $subject;
            }
        }

        // Gesammelt, nicht je Zeile: sonst kostet eine Monatsansicht so viele
        // Abfragen, wie sie Termine hat.
        return $this->subjects->resolveAll($refs);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function actorId(?object $actor): ?Uuid
    {
        return $actor instanceof ActorInterface && Uuid::isValid($actor->actorId())
            ? Uuid::fromString($actor->actorId())
            : null;
    }

    private function actorTeamId(?object $actor): ?Uuid
    {
        if (!$actor instanceof ActorInterface) {
            return null;
        }

        $teamId = $actor->actorTeamId();

        return null !== $teamId && Uuid::isValid($teamId) ? Uuid::fromString($teamId) : null;
    }
}
