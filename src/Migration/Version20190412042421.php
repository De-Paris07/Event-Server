<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190412042421 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE client ADD success_address VARCHAR(255) NOT NULL COMMENT \'Адресс уведомления об удачном выполнении события сервисами\', ADD fail_address VARCHAR(255) NOT NULL COMMENT \'Адресс уведомления о неудачном выполнении события сервисами\', DROP status, CHANGE delivery_type delivery_type INT NOT NULL COMMENT \'Метод доставки события\', CHANGE delivery_address delivery_address VARCHAR(255) NOT NULL COMMENT \'Адресс доставки события\', CHANGE client_token client_token VARCHAR(255) DEFAULT NULL COMMENT \'Токен для аутентификации сервера\', CHANGE server_token server_token VARCHAR(255) DEFAULT NULL COMMENT \'Токен для аутентификации клиента\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE client ADD status TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Статус\', DROP success_address, DROP fail_address, CHANGE delivery_type delivery_type INT NOT NULL COMMENT \'Метод доставки\', CHANGE delivery_address delivery_address VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci COMMENT \'Адресс доставки\', CHANGE client_token client_token VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'Токен для аутентификации клиента\', CHANGE server_token server_token VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'Токен для аутентификации сервера\'');
    }
}
