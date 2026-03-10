<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260309170220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE guest_user (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE registered_customer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) NOT NULL, total_spent DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_ED3ED419444F97DD (phone), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE `order` ADD registered_customer_id INT DEFAULT NULL, ADD guest_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F529939851834092 FOREIGN KEY (registered_customer_id) REFERENCES registered_customer (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398E7AB17D9 FOREIGN KEY (guest_user_id) REFERENCES guest_user (id)');
        $this->addSql('CREATE INDEX IDX_F529939851834092 ON `order` (registered_customer_id)');
        $this->addSql('CREATE INDEX IDX_F5299398E7AB17D9 ON `order` (guest_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE guest_user');
        $this->addSql('DROP TABLE registered_customer');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F529939851834092');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398E7AB17D9');
        $this->addSql('DROP INDEX IDX_F529939851834092 ON `order`');
        $this->addSql('DROP INDEX IDX_F5299398E7AB17D9 ON `order`');
        $this->addSql('ALTER TABLE `order` DROP registered_customer_id, DROP guest_user_id');
    }
}
