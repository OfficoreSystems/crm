<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modul company: Firmenstammdaten.
 *
 * Die address_-Spalten stammen aus dem Embeddable Address. Eine eigene
 * Tabelle waere hier falsch: eine Anschrift hat keine Identitaet, sie gehoert
 * der Firma.
 */
final class Version20260812165136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'company: Tabelle company_companies anlegen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE company_companies (
              id UUID NOT NULL,
              name VARCHAR(180) NOT NULL,
              industry VARCHAR(120) DEFAULT NULL,
              website VARCHAR(255) DEFAULT NULL,
              email VARCHAR(180) DEFAULT NULL,
              phone VARCHAR(60) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              address_street VARCHAR(180) DEFAULT NULL,
              address_postal_code VARCHAR(20) DEFAULT NULL,
              address_city VARCHAR(120) DEFAULT NULL,
              address_country VARCHAR(2) DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        // Name: die Liste sortiert danach. Branche: Auswertungen gruppieren
        // danach, und ohne Index wird das mit wachsender Tabelle ein Seq Scan.
        $this->addSql('CREATE INDEX idx_company_name ON company_companies (name)');
        $this->addSql('CREATE INDEX idx_company_industry ON company_companies (industry)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company_companies');
    }
}
