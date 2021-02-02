<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190404131808 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE client (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL COMMENT \'Наименование\', delivery_type INT NOT NULL COMMENT \'Метод доставки\', delivery_address VARCHAR(255) NOT NULL COMMENT \'Адресс доставки\', status TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Статус\', created_at DATETIME NOT NULL COMMENT \'Дата создания\', UNIQUE INDEX name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client_event_type (client_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', event_type_id INT NOT NULL, INDEX IDX_34126C319EB6921 (client_id), INDEX IDX_34126C3401B253C (event_type_id), PRIMARY KEY(client_id, event_type_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL COMMENT \'Наименование\', UNIQUE INDEX name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE client_event_type ADD CONSTRAINT FK_34126C319EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_event_type ADD CONSTRAINT FK_34126C3401B253C FOREIGN KEY (event_type_id) REFERENCES event_type (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE client_event_type DROP FOREIGN KEY FK_34126C319EB6921');
        $this->addSql('ALTER TABLE client_event_type DROP FOREIGN KEY FK_34126C3401B253C');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE client_event_type');
        $this->addSql('DROP TABLE event_type');
    }
}
