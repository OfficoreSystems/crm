<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modul activity: Timeline-Eintraege.
 *
 * subject_type und subject_id bilden zusammen einen polymorphen Verweis -
 * mal auf einen Kontakt, mal auf eine Firma, mal auf eine Verkaufschance.
 * Ein Fremdschluessel ist dafuer schon technisch unmoeglich, unabhaengig von
 * der Modulgrenze. Der gemeinsame Index ueber beide Spalten ist deshalb die
 * einzige Absicherung, die es gibt.
 */
final class Version20260812210757 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activity: Tabelle activity_activities anlegen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE activity_activities (
              id UUID NOT NULL,
              type VARCHAR(20) NOT NULL,
              subject_type VARCHAR(40) NOT NULL,
              subject_id VARCHAR(64) NOT NULL,
              summary VARCHAR(200) NOT NULL,
              body TEXT DEFAULT NULL,
              author_id UUID DEFAULT NULL,
              occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_activity_subject ON activity_activities (subject_type, subject_id)');
        $this->addSql('CREATE INDEX idx_activity_occurred ON activity_activities (occurred_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_activities');
    }
}
