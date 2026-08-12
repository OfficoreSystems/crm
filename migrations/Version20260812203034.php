<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modul deal: Verkaufschancen.
 *
 * value_amount ist BIGINT und haelt Cent, nicht Euro. Fliesskomma waere fuer
 * Geld untauglich - die Abweichungen summieren sich ueber eine Pipeline zu
 * Betraegen, die niemand erklaeren kann.
 *
 * company_id, contact_id und owner_id zeigen in andere Module und haben
 * deshalb bewusst keinen Fremdschluessel.
 */
final class Version20260812203034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'deal: Tabelle deal_deals anlegen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE deal_deals (
              id UUID NOT NULL,
              title VARCHAR(200) NOT NULL,
              stage VARCHAR(20) NOT NULL,
              company_id UUID DEFAULT NULL,
              contact_id UUID DEFAULT NULL,
              owner_id UUID DEFAULT NULL,
              expected_close_date DATE DEFAULT NULL,
              closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              value_amount BIGINT NOT NULL,
              value_currency VARCHAR(3) NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_deal_stage ON deal_deals (stage)');
        $this->addSql('CREATE INDEX idx_deal_company ON deal_deals (company_id)');
        $this->addSql('CREATE INDEX idx_deal_owner ON deal_deals (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE deal_deals');
    }
}
