<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Berechtigungen: Team-Spalte an besitzbaren Datensaetzen.
 *
 * Das Team liegt am Datensatz und wird nicht ueber den Besitzer aufgeloest.
 * Der Doctrine-Filter schraenkt Listen direkt in SQL ein und kann dabei keinen
 * Finder aufrufen - er braucht eine Spalte. Fachlich passt es ohnehin: eine
 * Chance bleibt bei dem Team, das sie bearbeitet hat, auch wenn der Besitzer
 * spaeter wechselt.
 *
 * Bestandsdaten bleiben ohne Team. Sie sind damit nur mit ALL-Rechten
 * sichtbar, was die sichere Voreinstellung ist - nicht die bequeme.
 */
final class Version20260812225751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Berechtigungen: owner_team_id und author_team_id ergaenzen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_activities ADD author_team_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_activity_author_team ON activity_activities (author_team_id)');
        $this->addSql('ALTER TABLE deal_deals ADD owner_team_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_deal_owner_team ON deal_deals (owner_team_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_activity_author_team');
        $this->addSql('ALTER TABLE activity_activities DROP author_team_id');
        $this->addSql('DROP INDEX idx_deal_owner_team');
        $this->addSql('ALTER TABLE deal_deals DROP owner_team_id');
    }
}
