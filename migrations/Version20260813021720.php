<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813021720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_documents (id UUID NOT NULL, subject_type VARCHAR(40) NOT NULL, subject_id VARCHAR(64) NOT NULL, filename VARCHAR(255) NOT NULL, mime_type VARCHAR(127) NOT NULL, size INT NOT NULL, storage_key VARCHAR(255) NOT NULL, owner_id UUID DEFAULT NULL, owner_team_id UUID DEFAULT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B0885A29111795A5 ON document_documents (storage_key)');
        $this->addSql('CREATE INDEX idx_document_subject ON document_documents (subject_type, subject_id)');
        $this->addSql('CREATE INDEX idx_document_owner ON document_documents (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE document_documents');
    }
}
