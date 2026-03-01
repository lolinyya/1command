<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260301210908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, last_name, first_name, age, email, status_id FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, age INTEGER DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, status_id INTEGER DEFAULT NULL, CONSTRAINT FK_8D93D6496BF700BD FOREIGN KEY (status_id) REFERENCES status (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO user (id, last_name, first_name, age, email, status_id) SELECT id, last_name, first_name, age, email, status_id FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE INDEX IDX_8D93D6496BF700BD ON user (status_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, last_name, first_name, age, email, status_id FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER DEFAULT NULL, last_name CLOB DEFAULT NULL, first_name CLOB DEFAULT NULL, age INTEGER DEFAULT NULL, email CLOB DEFAULT NULL, status_id INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO user (id, last_name, first_name, age, email, status_id) SELECT id, last_name, first_name, age, email, status_id FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }
}
