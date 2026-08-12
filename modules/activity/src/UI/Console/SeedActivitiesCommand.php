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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $created = 0;

        foreach ($subjects as $index => $subject) {
            [$type, $summary, $body, $offsetDays] = self::TEMPLATES[$index % \count(self::TEMPLATES)];

            ($this->logActivity)(new LogActivityCommand(
                type: ActivityType::from($type),
                subject: new SubjectRef($subject->type, $subject->id),
                summary: sprintf($summary, $subject->label),
                body: $body,
                occurredAt: $now->modify(sprintf('%+d days', $offsetDays)),
            ));

            ++$created;
        }

        $io->success(sprintf('%d Beispielaktivitaeten angelegt.', $created));
        $io->note(sprintf('Verteilt auf: %s.', implode(', ', array_keys($this->subjects->supportedTypes()))));

        return Command::SUCCESS;
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
