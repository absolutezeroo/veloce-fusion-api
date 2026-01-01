<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for refresh tokens table.
 */
final class Version20260101000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create veloce_refresh_tokens table for JWT refresh token storage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE veloce_refresh_tokens (
            id INT AUTO_INCREMENT NOT NULL,
            token VARCHAR(128) NOT NULL,
            user_id INT NOT NULL,
            expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            UNIQUE INDEX UNIQ_REFRESH_TOKEN (token),
            INDEX idx_refresh_token_expires (expires_at),
            INDEX idx_refresh_token_user (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_REFRESH_TOKEN_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS veloce_refresh_tokens');
    }
}
