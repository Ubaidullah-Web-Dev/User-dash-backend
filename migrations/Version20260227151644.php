<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227151644 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, mail VARCHAR(255) NOT NULL, phone INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY `FK_D34A04ADCCBBC969`');
        $this->addSql('ALTER TABLE product_unit DROP FOREIGN KEY `FK_51532EF24584665A`');
        $this->addSql('ALTER TABLE product_unit DROP FOREIGN KEY `FK_51532EF2F8BD700D`');
        $this->addSql('ALTER TABLE unit_conversion DROP FOREIGN KEY `FK_BE39B18476254DF8`');
        $this->addSql('ALTER TABLE unit_conversion DROP FOREIGN KEY `FK_BE39B1847EE393A2`');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_group');
        $this->addSql('DROP TABLE product_group_product');
        $this->addSql('DROP TABLE product_unit');
        $this->addSql('DROP TABLE unit_conversion');
        $this->addSql('DROP TABLE unit_entity');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, base_unit_id INT NOT NULL, price INT NOT NULL, quantity INT NOT NULL, slug VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, code INT NOT NULL, status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_D34A04ADCCBBC969 (base_unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE product_group (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, code INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE product_group_product (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, group_id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE product_unit (id INT AUTO_INCREMENT NOT NULL, is_primary TINYINT NOT NULL, product_id INT NOT NULL, unit_id INT NOT NULL, INDEX IDX_51532EF24584665A (product_id), INDEX IDX_51532EF2F8BD700D (unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE unit_conversion (id INT AUTO_INCREMENT NOT NULL, factor DOUBLE PRECISION NOT NULL, from_unit_id INT NOT NULL, to_unit_id INT NOT NULL, INDEX IDX_BE39B1847EE393A2 (from_unit_id), INDEX IDX_BE39B18476254DF8 (to_unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE unit_entity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, symbol VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT `FK_D34A04ADCCBBC969` FOREIGN KEY (base_unit_id) REFERENCES unit_entity (id)');
        $this->addSql('ALTER TABLE product_unit ADD CONSTRAINT `FK_51532EF24584665A` FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE product_unit ADD CONSTRAINT `FK_51532EF2F8BD700D` FOREIGN KEY (unit_id) REFERENCES unit_entity (id)');
        $this->addSql('ALTER TABLE unit_conversion ADD CONSTRAINT `FK_BE39B18476254DF8` FOREIGN KEY (to_unit_id) REFERENCES unit_entity (id)');
        $this->addSql('ALTER TABLE unit_conversion ADD CONSTRAINT `FK_BE39B1847EE393A2` FOREIGN KEY (from_unit_id) REFERENCES unit_entity (id)');
        $this->addSql('DROP TABLE users');
    }
}
