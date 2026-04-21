<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260421124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds missing columns to order and order_item tables that were missing in production.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` ADD previous_balance_payment DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE order_item ADD discount_percentage DOUBLE PRECISION DEFAULT NULL, ADD discount_amount DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP previous_balance_payment');
        $this->addSql('ALTER TABLE order_item DROP discount_percentage, DROP discount_amount');
    }
}
