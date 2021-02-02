<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191206145002 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE retry_event (id INT AUTO_INCREMENT NOT NULL, count_attempts INT NOT NULL, event_id VARCHAR(255) NOT NULL, delivery_type INT NOT NULL, delivery_address VARCHAR(255) NOT NULL, priority INT NOT NULL, event_name VARCHAR(255) NOT NULL, data LONGTEXT NOT NULL, created DATETIME NOT NULL, retry_date INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event ADD data LONGTEXT NOT NULL COMMENT \'Оригинальное событие\', ADD retry_date INT DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE retry_event');
        $this->addSql('ALTER TABLE event DROP data, DROP retry_date');
    }
}
