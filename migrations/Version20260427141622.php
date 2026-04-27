<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427141622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cash_recovery (id INT AUTO_INCREMENT NOT NULL, amount DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, remarks LONGTEXT DEFAULT NULL, registered_customer_id INT NOT NULL, user_id INT NOT NULL, company_id INT NOT NULL, INDEX IDX_91C0620451834092 (registered_customer_id), INDEX IDX_91C06204A76ED395 (user_id), INDEX IDX_91C06204979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE cash_recovery ADD CONSTRAINT FK_91C0620451834092 FOREIGN KEY (registered_customer_id) REFERENCES registered_customer (id)');
        $this->addSql('ALTER TABLE cash_recovery ADD CONSTRAINT FK_91C06204A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE cash_recovery ADD CONSTRAINT FK_91C06204979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_recovery DROP FOREIGN KEY FK_91C0620451834092');
        $this->addSql('ALTER TABLE cash_recovery DROP FOREIGN KEY FK_91C06204A76ED395');
        $this->addSql('ALTER TABLE cash_recovery DROP FOREIGN KEY FK_91C06204979B1AD6');
        $this->addSql('DROP TABLE cash_recovery');
    }
}
