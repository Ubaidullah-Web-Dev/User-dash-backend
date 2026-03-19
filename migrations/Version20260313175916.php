<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260313175916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_item CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE product_image CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE reset_password_token CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE vendor_order CHANGE company_id company_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_item CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE product_image CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE reset_password_token CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE `user` CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE vendor_order CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
    }
}
