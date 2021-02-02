<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190405095739 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE client ADD client_token VARCHAR(255) DEFAULT NULL COMMENT \'Токен для аутентификации клиента\', ADD server_token VARCHAR(255) DEFAULT NULL COMMENT \'Токен для аутентификации сервера\'');
        $this->addSql('CREATE UNIQUE INDEX client_token ON client (client_token)');
        $this->addSql('CREATE UNIQUE INDEX server_token ON client (server_token)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX client_token ON client');
        $this->addSql('DROP INDEX server_token ON client');
        $this->addSql('ALTER TABLE client DROP client_token, DROP server_token');
    }
}
