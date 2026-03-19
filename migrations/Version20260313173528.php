<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260313173528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_64C19C1979B1AD6 ON category (company_id)');
        $this->addSql('DROP INDEX UNIQ_F4F078795FA1E697 ON global_setting');
        $this->addSql('ALTER TABLE global_setting ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE global_setting ADD CONSTRAINT FK_F4F07879979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_F4F07879979B1AD6 ON global_setting (company_id)');
        $this->addSql('ALTER TABLE guest_user ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE guest_user ADD CONSTRAINT FK_A9298B14979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_A9298B14979B1AD6 ON guest_user (company_id)');
        $this->addSql('ALTER TABLE `order` ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_F5299398979B1AD6 ON `order` (company_id)');
        $this->addSql('ALTER TABLE order_item ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_52EA1F09979B1AD6 ON order_item (company_id)');
        $this->addSql('ALTER TABLE post ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8D979B1AD6 ON post (company_id)');
        $this->addSql('ALTER TABLE product ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_D34A04AD979B1AD6 ON product (company_id)');
        $this->addSql('ALTER TABLE registered_customer ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE registered_customer ADD CONSTRAINT FK_ED3ED419979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_ED3ED419979B1AD6 ON registered_customer (company_id)');
        $this->addSql('ALTER TABLE vendor ADD company_id INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE vendor ADD CONSTRAINT FK_F52233F6979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('CREATE INDEX IDX_F52233F6979B1AD6 ON vendor (company_id)');
        
        // Update data to ensure no NULLs exist before the constraint is strictly enforced (though DEFAULT 1 should handle it)
        $tables = ['category', 'global_setting', 'guest_user', '`order`', 'order_item', 'post', 'product', 'registered_customer', 'vendor'];
        foreach ($tables as $table) {
            $this->addSql("UPDATE $table SET company_id = 1 WHERE company_id IS NULL");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1979B1AD6');
        $this->addSql('DROP INDEX IDX_64C19C1979B1AD6 ON category');
        $this->addSql('ALTER TABLE category DROP company_id');
        $this->addSql('ALTER TABLE global_setting DROP FOREIGN KEY FK_F4F07879979B1AD6');
        $this->addSql('DROP INDEX IDX_F4F07879979B1AD6 ON global_setting');
        $this->addSql('ALTER TABLE global_setting DROP company_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F4F078795FA1E697 ON global_setting (setting_key)');
        $this->addSql('ALTER TABLE guest_user DROP FOREIGN KEY FK_A9298B14979B1AD6');
        $this->addSql('DROP INDEX IDX_A9298B14979B1AD6 ON guest_user');
        $this->addSql('ALTER TABLE guest_user DROP company_id');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398979B1AD6');
        $this->addSql('DROP INDEX IDX_F5299398979B1AD6 ON `order`');
        $this->addSql('ALTER TABLE `order` DROP company_id');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09979B1AD6');
        $this->addSql('DROP INDEX IDX_52EA1F09979B1AD6 ON order_item');
        $this->addSql('ALTER TABLE order_item DROP company_id');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D979B1AD6');
        $this->addSql('DROP INDEX IDX_5A8A6C8D979B1AD6 ON post');
        $this->addSql('ALTER TABLE post DROP company_id');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD979B1AD6');
        $this->addSql('DROP INDEX IDX_D34A04AD979B1AD6 ON product');
        $this->addSql('ALTER TABLE product DROP company_id');
        $this->addSql('ALTER TABLE registered_customer DROP FOREIGN KEY FK_ED3ED419979B1AD6');
        $this->addSql('DROP INDEX IDX_ED3ED419979B1AD6 ON registered_customer');
        $this->addSql('ALTER TABLE registered_customer DROP company_id');
        $this->addSql('ALTER TABLE vendor DROP FOREIGN KEY FK_F52233F6979B1AD6');
        $this->addSql('DROP INDEX IDX_F52233F6979B1AD6 ON vendor');
        $this->addSql('ALTER TABLE vendor DROP company_id');
    }
}
