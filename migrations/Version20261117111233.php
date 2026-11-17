<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117111233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial mediatheque schema: 13 tables, foreign keys, indexes and concurrency constraints';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE branch (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contributor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE copy (id INT AUTO_INCREMENT NOT NULL, barcode VARCHAR(64) NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, edition_id INT NOT NULL, current_branch_id INT NOT NULL, UNIQUE INDEX UNIQ_4DBABB8297AE0266 (barcode), INDEX idx_copy_status (status), INDEX IDX_4DBABB8274281A5E (edition_id), INDEX IDX_4DBABB827F07DE5 (current_branch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE edition (id INT AUTO_INCREMENT NOT NULL, isbn VARCHAR(20) DEFAULT NULL, title VARCHAR(255) NOT NULL, publisher VARCHAR(255) NOT NULL, publication_year INT NOT NULL, media_type VARCHAR(255) NOT NULL, cover_url VARCHAR(2048) DEFAULT NULL, completeness_status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, work_id INT NOT NULL, UNIQUE INDEX UNIQ_A891181FCC1CF4E6 (isbn), INDEX idx_edition_title (title), INDEX IDX_A891181FBB3453DB (work_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE edition_contributor (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) NOT NULL, position INT NOT NULL, edition_id INT NOT NULL, contributor_id INT NOT NULL, UNIQUE INDEX uniq_edition_contributor_role (edition_id, contributor_id, role), INDEX IDX_9F7EB14D74281A5E (edition_id), INDEX IDX_9F7EB14D7A19A357 (contributor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE loan (id INT AUTO_INCREMENT NOT NULL, active_copy_id INT DEFAULT NULL, borrowed_at DATETIME NOT NULL, due_date DATETIME NOT NULL, returned_at DATETIME DEFAULT NULL, extension_count INT NOT NULL, copy_id INT NOT NULL, member_id INT NOT NULL, UNIQUE INDEX UNIQ_C5D30D032B399B6C (active_copy_id), INDEX idx_loan_member_returned (member_id, returned_at), INDEX idx_loan_due_date (due_date), INDEX IDX_C5D30D03A8752772 (copy_id), INDEX IDX_C5D30D037597D3FE (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE member (id INT AUTO_INCREMENT NOT NULL, membership_number VARCHAR(64) NOT NULL, subscription_ends_at DATETIME NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, plan_id INT NOT NULL, home_branch_id INT NOT NULL, UNIQUE INDEX UNIQ_70E4FA78E82DAECD (membership_number), UNIQUE INDEX UNIQ_70E4FA78A76ED395 (user_id), INDEX IDX_70E4FA78E899029B (plan_id), INDEX IDX_70E4FA78A202B50D (home_branch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE penalty (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, reason VARCHAR(255) NOT NULL, is_paid TINYINT NOT NULL, paid_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, loan_id INT NOT NULL, INDEX IDX_AFE28FD8CE73868F (loan_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE plan (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, max_simultaneous_loans INT NOT NULL, loan_duration_days INT NOT NULL, max_extensions INT NOT NULL, extension_duration_days INT NOT NULL, penalty_rate_per_day NUMERIC(10, 2) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, active_member_edition_key VARCHAR(64) DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, pickup_deadline_at DATETIME DEFAULT NULL, fulfilled_at DATETIME DEFAULT NULL, edition_id INT NOT NULL, member_id INT NOT NULL, pickup_branch_id INT NOT NULL, assigned_copy_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_42C8495539867541 (active_member_edition_key), INDEX idx_reservation_edition_status_created (edition_id, status, created_at), INDEX idx_reservation_pickup_deadline (pickup_deadline_at), INDEX IDX_42C8495574281A5E (edition_id), INDEX IDX_42C849557597D3FE (member_id), INDEX IDX_42C849552E1CC4FA (pickup_branch_id), INDEX IDX_42C84955A2ED9FFD (assigned_copy_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transfer (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, requested_at DATETIME NOT NULL, shipped_at DATETIME DEFAULT NULL, received_at DATETIME DEFAULT NULL, copy_id INT NOT NULL, source_branch_id INT NOT NULL, target_branch_id INT NOT NULL, reservation_id INT DEFAULT NULL, INDEX idx_transfer_status (status), INDEX IDX_4034A3C0A8752772 (copy_id), INDEX IDX_4034A3C0B4485F87 (source_branch_id), INDEX IDX_4034A3C0E862BE65 (target_branch_id), INDEX IDX_4034A3C0B83297E7 (reservation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE work (id INT AUTO_INCREMENT NOT NULL, original_title VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE copy ADD CONSTRAINT FK_4DBABB8274281A5E FOREIGN KEY (edition_id) REFERENCES edition (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE copy ADD CONSTRAINT FK_4DBABB827F07DE5 FOREIGN KEY (current_branch_id) REFERENCES branch (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE edition ADD CONSTRAINT FK_A891181FBB3453DB FOREIGN KEY (work_id) REFERENCES work (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE edition_contributor ADD CONSTRAINT FK_9F7EB14D74281A5E FOREIGN KEY (edition_id) REFERENCES edition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE edition_contributor ADD CONSTRAINT FK_9F7EB14D7A19A357 FOREIGN KEY (contributor_id) REFERENCES contributor (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D03A8752772 FOREIGN KEY (copy_id) REFERENCES copy (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D037597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA78A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA78E899029B FOREIGN KEY (plan_id) REFERENCES plan (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA78A202B50D FOREIGN KEY (home_branch_id) REFERENCES branch (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE penalty ADD CONSTRAINT FK_AFE28FD8CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495574281A5E FOREIGN KEY (edition_id) REFERENCES edition (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849557597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552E1CC4FA FOREIGN KEY (pickup_branch_id) REFERENCES branch (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A2ED9FFD FOREIGN KEY (assigned_copy_id) REFERENCES copy (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0A8752772 FOREIGN KEY (copy_id) REFERENCES copy (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0B4485F87 FOREIGN KEY (source_branch_id) REFERENCES branch (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0E862BE65 FOREIGN KEY (target_branch_id) REFERENCES branch (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE copy DROP FOREIGN KEY FK_4DBABB8274281A5E');
        $this->addSql('ALTER TABLE copy DROP FOREIGN KEY FK_4DBABB827F07DE5');
        $this->addSql('ALTER TABLE edition DROP FOREIGN KEY FK_A891181FBB3453DB');
        $this->addSql('ALTER TABLE edition_contributor DROP FOREIGN KEY FK_9F7EB14D74281A5E');
        $this->addSql('ALTER TABLE edition_contributor DROP FOREIGN KEY FK_9F7EB14D7A19A357');
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D03A8752772');
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D037597D3FE');
        $this->addSql('ALTER TABLE member DROP FOREIGN KEY FK_70E4FA78A76ED395');
        $this->addSql('ALTER TABLE member DROP FOREIGN KEY FK_70E4FA78E899029B');
        $this->addSql('ALTER TABLE member DROP FOREIGN KEY FK_70E4FA78A202B50D');
        $this->addSql('ALTER TABLE penalty DROP FOREIGN KEY FK_AFE28FD8CE73868F');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495574281A5E');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849557597D3FE');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552E1CC4FA');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A2ED9FFD');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0A8752772');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0B4485F87');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0E862BE65');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0B83297E7');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE branch');
        $this->addSql('DROP TABLE contributor');
        $this->addSql('DROP TABLE copy');
        $this->addSql('DROP TABLE edition');
        $this->addSql('DROP TABLE edition_contributor');
        $this->addSql('DROP TABLE loan');
        $this->addSql('DROP TABLE member');
        $this->addSql('DROP TABLE penalty');
        $this->addSql('DROP TABLE plan');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE transfer');
        $this->addSql('DROP TABLE work');
    }
}
