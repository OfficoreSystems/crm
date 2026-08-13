<?php

declare(strict_types=1);

namespace Crm\Calendar\UI\Controller;

use Crm\Calendar\CalendarModule;
use Crm\Calendar\Domain\AppointmentRepositoryInterface;
use Crm\Calendar\Domain\CalendarFeed;
use Crm\Calendar\Domain\CalendarFeedRepositoryInterface;
use Crm\Calendar\Infrastructure\Ics\IcsWriter;
use Crm\SharedKernel\User\UserFinderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Der ICS-Feed - die einzige Seite dieser Anwendung ohne Anmeldung.
 *
 * Outlook, Google und Apple rufen eine URL ab und bringen keine Sitzung mit.
 * Der Ausweis ist das Token in der URL, und daraus folgen drei Regeln, die
 * hier ohne Ausnahme gelten:
 *
 *   1. Es wird nach dem *Hash* gesucht, nie nach dem Klartext.
 *   2. Ausgeliefert werden nur die Termine, die diesem einen Benutzer
 *      gehoeren - ausdruecklich in der Abfrage, nicht ueber den
 *      Doctrine-Sichtbarkeitsfilter. Der ist hier abgeschaltet, weil es
 *      keinen angemeldeten Benutzer gibt, an dem er sich orientieren
 *      koennte. Wer sich hier auf ihn verliesse, lieferte alle Termine
 *      aller Benutzer aus.
 *   3. Ein unbekanntes Token bekommt 404, keine Erklaerung. Ein
 *      "Token abgelaufen" waere die Bestaetigung, dass es das Token gab.
 */
#[Route(CalendarModule::FEED_PREFIX, name: 'calendar_')]
final class FeedController extends AbstractController
{
    /**
     * Wie weit der Feed zurueck- und vorausschaut.
     *
     * Nicht alles: ein Kalender mit zehn Jahren Historie macht jeden Abruf
     * langsam, und kein Client zeigt das an.
     */
    private const PAST = '-1 month';
    private const FUTURE = '+12 months';

    public function __construct(
        private readonly CalendarFeedRepositoryInterface $feeds,
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly UserFinderInterface $users,
        private readonly IcsWriter $ics,
    ) {
    }

    #[Route('/{token}.ics', name: 'feed', methods: ['GET'])]
    public function feed(string $token): Response
    {
        $feed = $this->feeds->findByTokenHash(CalendarFeed::hash($token));

        if (null === $feed) {
            // Bewusst wortlos: jede genauere Auskunft waere eine Auskunft.
            throw $this->createNotFoundException();
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $appointments = $this->appointments->findForOwnerBetween(
            $feed->userId(),
            $now->modify(self::PAST),
            $now->modify(self::FUTURE),
        );

        $this->rememberUse($feed, $now);

        $owner = $this->users->find($feed->userId()->toString());

        $response = new Response($this->ics->calendar(
            $appointments,
            null === $owner ? 'Officore' : 'Officore – '.$owner->name,
            $now,
        ));

        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        // Als Datei benannt, damit ein Doppelklick im Browser die richtige
        // Anwendung oeffnet statt Text anzuzeigen.
        $response->headers->set('Content-Disposition', 'inline; filename="officore.ics"');
        // Kein Zwischenspeicher: der Kalender aendert sich, und die URL ist
        // ein Geheimnis - sie hat in keinem gemeinsamen Cache etwas verloren.
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * Merkt sich den Abruf - aber hoechstens stuendlich.
     *
     * Kalenderclients fragen im Viertelstundentakt. Ein Schreibzugriff je
     * Abruf waere eine erstaunliche Menge Schreiblast fuer eine Information,
     * die niemand minutengenau braucht.
     */
    private function rememberUse(CalendarFeed $feed, \DateTimeImmutable $now): void
    {
        $last = $feed->lastUsedAt();

        if (null !== $last && $last > $now->modify('-1 hour')) {
            return;
        }

        $feed->markUsed();
        $this->feeds->save($feed);
    }
}
