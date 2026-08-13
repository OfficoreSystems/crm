<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813033655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE calendar_appointments (id UUID NOT NULL, title VARCHAR(200) NOT NULL, description TEXT DEFAULT NULL, location VARCHAR(200) DEFAULT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, all_day BOOLEAN NOT NULL, subject_type VARCHAR(40) DEFAULT NULL, subject_id VARCHAR(64) DEFAULT NULL, owner_id UUID DEFAULT NULL, owner_team_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sequence INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_appointment_starts_at ON calendar_appointments (starts_at)');
        $this->addSql('CREATE INDEX idx_appointment_subject ON calendar_appointments (subject_type, subject_id)');
        $this->addSql('CREATE INDEX idx_appointment_owner ON calendar_appointments (owner_id)');
        $this->addSql('CREATE TABLE calendar_feeds (id UUID NOT NULL, user_id UUID NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_feed_user ON calendar_feeds (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_feed_token ON calendar_feeds (token_hash)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE calendar_appointments');
        $this->addSql('DROP TABLE calendar_feeds');
    }
}
