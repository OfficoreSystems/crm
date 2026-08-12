<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modul user: Benutzer und Teams.
 *
 * Der Fremdschluessel von user_users auf user_teams ist zulaessig, weil beide
 * Tabellen zum selben Modul gehoeren. Ueber Modulgrenzen hinweg stuenden hier
 * skalare UUID-Spalten ohne Constraint - sonst liesse sich ein Modul nicht
 * mehr entfernen, ohne die Datenbank zu zerlegen.
 */
final class Version20260805201832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'user: Tabellen user_teams und user_users anlegen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_teams (
              id UUID NOT NULL,
              name VARCHAR(120) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7400D9005E237E06 ON user_teams (name)');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_users (
              id UUID NOT NULL,
              email VARCHAR(180) NOT NULL,
              name VARCHAR(120) NOT NULL,
              roles JSON NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              active BOOLEAN NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              team_id UUID DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F6415EB1E7927C74 ON user_users (email)');
        $this->addSql('CREATE INDEX idx_user_team ON user_users (team_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_users
            ADD
              CONSTRAINT FK_F6415EB1296CD8AE FOREIGN KEY (team_id) REFERENCES user_teams (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_users DROP CONSTRAINT FK_F6415EB1296CD8AE');
        $this->addSql('DROP TABLE user_teams');
        $this->addSql('DROP TABLE user_users');
    }
}
