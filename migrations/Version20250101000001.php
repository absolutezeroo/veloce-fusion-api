<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Setting, Ban, and Authorization modules.
 */
final class Version20250101000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create veloce_settings, veloce_permissions, veloce_roles, veloce_roles_permission, veloce_roles_rank, veloce_roles_hierarchy tables';
    }

    public function up(Schema $schema): void
    {
        // veloce_settings
        $this->addSql('CREATE TABLE veloce_settings (
            id INT AUTO_INCREMENT NOT NULL,
            `key` VARCHAR(255) NOT NULL,
            value LONGTEXT NOT NULL,
            UNIQUE INDEX UNIQ_VELOCE_SETTINGS_KEY (`key`),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // veloce_permissions
        $this->addSql('CREATE TABLE veloce_permissions (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            status SMALLINT DEFAULT 1 NOT NULL,
            created_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_VELOCE_PERMISSIONS_NAME (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // veloce_roles
        $this->addSql('CREATE TABLE veloce_roles (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            status SMALLINT DEFAULT 1 NOT NULL,
            created_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_VELOCE_ROLES_NAME (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // veloce_roles_permission (pivot)
        $this->addSql('CREATE TABLE veloce_roles_permission (
            id INT AUTO_INCREMENT NOT NULL,
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX unique_role_permission (role_id, permission_id),
            INDEX IDX_ROLE_PERM_ROLE (role_id),
            INDEX IDX_ROLE_PERM_PERMISSION (permission_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_ROLE_PERM_ROLE FOREIGN KEY (role_id) REFERENCES veloce_roles (id) ON DELETE CASCADE,
            CONSTRAINT FK_ROLE_PERM_PERMISSION FOREIGN KEY (permission_id) REFERENCES veloce_permissions (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // veloce_roles_rank (pivot Role <-> User Rank)
        $this->addSql('CREATE TABLE veloce_roles_rank (
            id INT AUTO_INCREMENT NOT NULL,
            role_id INT NOT NULL,
            rank_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX unique_role_rank (role_id, rank_id),
            INDEX IDX_ROLE_RANK_ROLE (role_id),
            INDEX IDX_ROLE_RANK_RANK (rank_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_ROLE_RANK_ROLE FOREIGN KEY (role_id) REFERENCES veloce_roles (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // veloce_roles_hierarchy (inheritance)
        $this->addSql('CREATE TABLE veloce_roles_hierarchy (
            id INT AUTO_INCREMENT NOT NULL,
            parent_role_id INT NOT NULL,
            child_role_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX unique_hierarchy (parent_role_id, child_role_id),
            INDEX IDX_HIERARCHY_PARENT (parent_role_id),
            INDEX IDX_HIERARCHY_CHILD (child_role_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_HIERARCHY_PARENT FOREIGN KEY (parent_role_id) REFERENCES veloce_roles (id) ON DELETE CASCADE,
            CONSTRAINT FK_HIERARCHY_CHILD FOREIGN KEY (child_role_id) REFERENCES veloce_roles (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS veloce_roles_hierarchy');
        $this->addSql('DROP TABLE IF EXISTS veloce_roles_rank');
        $this->addSql('DROP TABLE IF EXISTS veloce_roles_permission');
        $this->addSql('DROP TABLE IF EXISTS veloce_roles');
        $this->addSql('DROP TABLE IF EXISTS veloce_permissions');
        $this->addSql('DROP TABLE IF EXISTS veloce_settings');
    }
}
