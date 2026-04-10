<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260410105144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lab_expenses (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, amount NUMERIC(12, 2) NOT NULL, expense_date DATE NOT NULL, created_at DATETIME NOT NULL, category VARCHAR(255) DEFAULT NULL, company_id INT NOT NULL, INDEX IDX_FE9AD8C6979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE lab_expenses ADD CONSTRAINT FK_FE9AD8C6979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lab_expenses DROP FOREIGN KEY FK_FE9AD8C6979B1AD6');
        $this->addSql('DROP TABLE lab_expenses');
    }
}
