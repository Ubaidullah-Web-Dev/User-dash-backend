<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260313175427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_item ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE2527979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_F0FE2527979B1AD6 ON cart_item (company_id)');
        $this->addSql('ALTER TABLE category CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE global_setting CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE guest_user CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE `order` CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE order_item CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE post CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE product CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE product_image ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F03979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_64617F03979B1AD6 ON product_image (company_id)');
        $this->addSql('ALTER TABLE registered_customer CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE reset_password_token ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE reset_password_token ADD CONSTRAINT FK_452C9EC5979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_452C9EC5979B1AD6 ON reset_password_token (company_id)');
        $this->addSql('ALTER TABLE user ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649979B1AD6 ON user (company_id)');
        $this->addSql('ALTER TABLE vendor CHANGE company_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE vendor_order ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE vendor_order ADD CONSTRAINT FK_E36F91D8979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_E36F91D8979B1AD6 ON vendor_order (company_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE2527979B1AD6');
        $this->addSql('DROP INDEX IDX_F0FE2527979B1AD6 ON cart_item');
        $this->addSql('ALTER TABLE cart_item DROP company_id');
        $this->addSql('ALTER TABLE category CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE global_setting CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE guest_user CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE `order` CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE order_item CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE post CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE product CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F03979B1AD6');
        $this->addSql('DROP INDEX IDX_64617F03979B1AD6 ON product_image');
        $this->addSql('ALTER TABLE product_image DROP company_id');
        $this->addSql('ALTER TABLE registered_customer CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE reset_password_token DROP FOREIGN KEY FK_452C9EC5979B1AD6');
        $this->addSql('DROP INDEX IDX_452C9EC5979B1AD6 ON reset_password_token');
        $this->addSql('ALTER TABLE reset_password_token DROP company_id');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649979B1AD6');
        $this->addSql('DROP INDEX IDX_8D93D649979B1AD6 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP company_id');
        $this->addSql('ALTER TABLE vendor CHANGE company_id company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE vendor_order DROP FOREIGN KEY FK_E36F91D8979B1AD6');
        $this->addSql('DROP INDEX IDX_E36F91D8979B1AD6 ON vendor_order');
        $this->addSql('ALTER TABLE vendor_order DROP company_id');
    }
}
