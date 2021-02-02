<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190603141254 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE event_subscribed (client_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', event_type_id INT NOT NULL, priority INT NOT NULL COMMENT \'Приоритет получения события сервисом\', INDEX IDX_13A371719EB6921 (client_id), INDEX IDX_13A3717401B253C (event_type_id), UNIQUE INDEX clientEventType (client_id, event_type_id), PRIMARY KEY(client_id, event_type_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_subscribed ADD CONSTRAINT FK_13A371719EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE event_subscribed ADD CONSTRAINT FK_13A3717401B253C FOREIGN KEY (event_type_id) REFERENCES event_type (id)');
        $this->addSql('DROP TABLE client_event_type');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE client_event_type (client_id CHAR(36) NOT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\', event_type_id INT NOT NULL, INDEX IDX_34126C319EB6921 (client_id), INDEX IDX_34126C3401B253C (event_type_id), PRIMARY KEY(client_id, event_type_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE client_event_type ADD CONSTRAINT FK_34126C319EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_event_type ADD CONSTRAINT FK_34126C3401B253C FOREIGN KEY (event_type_id) REFERENCES event_type (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE event_subscribed');
    }
}
