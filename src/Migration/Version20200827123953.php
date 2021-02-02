<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200827123953 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE event_subscribed ADD retry TINYINT(1) NOT NULL COMMENT \'Нужно ли повторить сообщение при ошибке обработки на сервисе\', ADD count_retry INT NOT NULL COMMENT \'Кол-во попыток повтора сообщения\', ADD interval_retry INT NOT NULL COMMENT \'Интервал между попытками повтора сообщения\', ADD priority_retry INT NOT NULL COMMENT \'Приоритет повторного сообщения в очереди\'');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA719EB6921');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA719EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA719EB6921');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA719EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE event_subscribed DROP retry, DROP count_retry, DROP interval_retry, DROP priority_retry');
    }
}
