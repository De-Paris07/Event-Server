<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190412093134 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE event (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', event_type_id INT NOT NULL, client_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', success_answer_count INT DEFAULT 0 COMMENT \'Количество ответов об удачно обработанном событии\', created_at DATETIME NOT NULL COMMENT \'Дата создания\', INDEX IDX_3BAE0AA7401B253C (event_type_id), INDEX IDX_3BAE0AA719EB6921 (client_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7401B253C FOREIGN KEY (event_type_id) REFERENCES event_type (id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA719EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE event');
    }
}
