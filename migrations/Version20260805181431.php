<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modul contact: Basistabelle fuer Kontakte.
 *
 * Migrationen liegen zentral unter migrations/, nicht im Modul: sie muessen
 * in einer definierten Gesamtreihenfolge laufen, und die kann ein einzelnes
 * Modul nicht kennen. Der Tabellenname traegt dafuer das Modulpraefix.
 */
final class Version20260805181431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'contact: Tabelle contact_contacts anlegen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE contact_contacts (
              id UUID NOT NULL,
              first_name VARCHAR(120) NOT NULL,
              last_name VARCHAR(120) NOT NULL,
              email VARCHAR(180) DEFAULT NULL,
              company VARCHAR(180) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);

        // Die Liste sortiert nach Nachname - ohne Index wird das mit
        // wachsender Tabelle ein Seq Scan bei jedem Tastendruck in der Suche.
        $this->addSql('CREATE INDEX idx_contact_last_name ON contact_contacts (last_name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contact_contacts');
    }
}
