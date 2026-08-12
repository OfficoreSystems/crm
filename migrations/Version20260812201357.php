<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modul contact: Freitext-Firma wird zur Referenz auf das company-Modul.
 *
 * Bewusst *kein* Fremdschluessel auf company_companies. Ein Constraint ueber
 * die Modulgrenze wuerde beide Module aneinanderketten: das company-Modul
 * liesse sich nicht mehr entfernen, ohne die contact-Tabelle zu zerlegen.
 * Die Gueltigkeit prueft stattdessen der Anwendungscode ueber
 * CompanyFinderInterface.
 *
 * Die Datenuebernahme ist gegen ein fehlendes company-Modul abgesichert.
 * Ohne dessen Tabelle gibt es nichts zuzuordnen - die Kontakte verlieren
 * dann ihre Firmenangabe, was in dieser Konstellation richtig ist.
 */
final class Version20260812201357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'contact: Freitextspalte company durch company_id ersetzen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_contacts ADD company_id UUID DEFAULT NULL');

        if ($schema->hasTable('company_companies')) {
            // Namensgleichheit ist das einzige Kriterium, das hier zur
            // Verfuegung steht. Firmennamen sind nicht eindeutig, deshalb
            // gewinnt der aelteste Treffer - v7-UUIDs sind zeitgeordnet.
            $this->addSql(<<<'SQL'
                UPDATE contact_contacts ct
                SET company_id = (
                  SELECT co.id
                  FROM company_companies co
                  WHERE co.name = ct.company
                  ORDER BY co.id ASC
                  LIMIT 1
                )
                WHERE ct.company IS NOT NULL
            SQL);
        }

        $this->addSql('ALTER TABLE contact_contacts DROP company');
        $this->addSql('CREATE INDEX idx_contact_company ON contact_contacts (company_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_contact_company');
        $this->addSql('ALTER TABLE contact_contacts ADD company VARCHAR(180) DEFAULT NULL');

        if ($schema->hasTable('company_companies')) {
            $this->addSql(<<<'SQL'
                UPDATE contact_contacts ct
                SET company = (
                  SELECT co.name
                  FROM company_companies co
                  WHERE co.id = ct.company_id
                )
                WHERE ct.company_id IS NOT NULL
            SQL);
        }

        $this->addSql('ALTER TABLE contact_contacts DROP company_id');
    }
}
