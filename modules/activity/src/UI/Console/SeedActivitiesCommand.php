<?php

declare(strict_types=1);

namespace Crm\Activity\UI\Console;

use Crm\Activity\Application\LogActivity;
use Crm\Activity\Application\LogActivityCommand;
use Crm\Activity\Domain\ActivityRepositoryInterface;
use Crm\Activity\Domain\ActivityType;
use Crm\SharedKernel\Subject\ResolvedSubject;
use Crm\SharedKernel\Subject\SubjectRef;
use Crm\SharedKernel\Subject\SubjectResolverRegistry;
use Crm\SharedKernel\User\UserFinderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Beispieleintraege, verteilt auf die Subjekte, die es gerade gibt.
 *
 * Der Befehl fragt die Registry, welche Typen aufloesbar sind, statt Kontakte
 * oder Firmen zu benennen - er laeuft damit auch, wenn nur eines der Module
 * installiert ist.
 */
#[AsCommand(
    name: 'activity:seed',
    description: 'Legt Beispielaktivitaeten an (idempotent: laeuft nur bei leerer Tabelle).',
)]
final class SeedActivitiesCommand extends Command
{
    /**
     * Vorlagen je Typ. Der Platzhalter %s wird durch den Namen des Subjekts
     * ersetzt.
     */
    private const TEMPLATES = [
        ['note', 'Gespraechsnotiz zu %s', 'Erstkontakt verlief positiv, Bedarf grundsaetzlich vorhanden.', -14],
        ['call', 'Rueckruf %s', 'Telefonat zur Terminabstimmung.', -9],
        ['meeting', 'Vor-Ort-Termin bei %s', 'Vorstellung des Leistungsumfangs.', -5],
        ['task', 'Angebot fuer %s nachfassen', null, 3],
        ['task', 'Unterlagen an %s senden', null, -2],
    ];

    public function __construct(
        private readonly LogActivity $logActivity,
        private readonly ActivityRepositoryInterface $activities,
        private readonly SubjectResolverRegistry $subjects,
        private readonly UserFinderInterface $users,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->activities->countAll() > 0) {
            $io->note('Es sind bereits Aktivitaeten vorhanden - es wird nichts angelegt.');

            return Command::SUCCESS;
        }

        $subjects = $this->collectSubjects();

        if ([] === $subjects) {
            $io->warning('Kein aufloesbares Subjekt gefunden. Sind contact, company oder deal installiert und geseedet?');

            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable();
        $authors = $this->collectAuthors();
        $created = 0;

        foreach ($subjects as $index => $subject) {
            [$type, $summary, $body, $offsetDays] = self::TEMPLATES[$index % \count(self::TEMPLATES)];
            $author = [] === $authors ? null : $authors[$index % \count($authors)];

            ($this->logActivity)(new LogActivityCommand(
                type: ActivityType::from($type),
                subject: new SubjectRef($subject->type, $subject->id),
                summary: sprintf($summary, $subject->label),
                body: $body,
                authorId: $author?->id,
                authorTeamId: $author?->teamId,
                occurredAt: $now->modify(sprintf('%+d days', $offsetDays)),
            ));

            ++$created;
        }

        $io->success(sprintf('%d Beispielaktivitaeten angelegt.', $created));
        $io->note(sprintf('Verteilt auf: %s.', implode(', ', array_keys($this->subjects->supportedTypes()))));

        if ([] === $authors) {
            $io->warning('Kein Autor zugeordnet - ohne user-Modul greifen die Sichtbarkeitsregeln nicht.');
        } else {
            $io->note(sprintf(
                'Autoren im Wechsel: %s.',
                implode(', ', array_map(static fn (SeedAuthor $a): string => $a->name, $authors)),
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * Alle Benutzer mit Team, im Wechsel als Autor.
     *
     * Absicht: die Eintraege sollen sich auf mehrere Teams verteilen. Gehoerte
     * die ganze Timeline einem einzigen Benutzer, waere nicht zu erkennen, ob
     * der Sichtbarkeitsfilter arbeitet oder nur zufaellig nichts wegnimmt.
     *
     * @return list<SeedAuthor>
     */
    private function collectAuthors(): array
    {
        $authors = [];

        foreach ($this->users->findAllActive() as $user) {
            if (!Uuid::isValid($user->id) || null === $user->teamId || !Uuid::isValid($user->teamId)) {
                continue;
            }

            $authors[] = new SeedAuthor(
                Uuid::fromString($user->id),
                Uuid::fromString($user->teamId),
                $user->name,
            );
        }

        return $authors;
    }

    /**
     * Bis zu drei Subjekte je Typ - genug fuer eine sichtbare Timeline, ohne
     * die Datenbank zu fluten.
     *
     * Hier steht kein Modulname: die Registry liefert, was installiert ist.
     *
     * @return list<ResolvedSubject>
     */
    private function collectSubjects(): array
    {
        return $this->subjects->searchAll(limitPerType: 3);
    }
}
