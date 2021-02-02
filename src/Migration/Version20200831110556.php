<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200831110556 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE unhandle_event (client_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', event_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', count_retry INT NOT NULL, INDEX IDX_6459544A19EB6921 (client_id), INDEX IDX_6459544A71F7E88B (event_id), UNIQUE INDEX clientEvent (client_id, event_id), PRIMARY KEY(client_id, event_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE unhandle_event ADD CONSTRAINT FK_6459544A19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE unhandle_event ADD CONSTRAINT FK_6459544A71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE unhandle_event');
    }
}
