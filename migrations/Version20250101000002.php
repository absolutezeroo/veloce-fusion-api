<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed initial roles and permissions for the RBAC system.
 */
final class Version20250101000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default roles, permissions, and assign them to ranks';
    }

    public function up(Schema $schema): void
    {
        $now = date('Y-m-d H:i:s');

        // Create default roles
        $this->addSql("INSERT INTO veloce_roles (name, description, status, created_at) VALUES
            ('SUPER_ADMIN', 'Full system access', 1, '$now'),
            ('ADMIN', 'Administrative access', 1, '$now'),
            ('MODERATOR', 'Moderation capabilities', 1, '$now'),
            ('STAFF', 'Basic staff access', 1, '$now'),
            ('USER', 'Regular user', 1, '$now')
        ");

        // Create default permissions
        $this->addSql("INSERT INTO veloce_permissions (name, description, status, created_at) VALUES
            -- Settings
            ('VIEW_SETTINGS', 'Can view settings', 1, '$now'),
            ('MANAGE_SETTINGS', 'Can manage settings', 1, '$now'),

            -- Users
            ('VIEW_USERS', 'Can view user list', 1, '$now'),
            ('MANAGE_USERS', 'Can manage users', 1, '$now'),

            -- Bans
            ('VIEW_BANS', 'Can view ban list', 1, '$now'),
            ('MANAGE_BANS', 'Can create/remove bans', 1, '$now'),

            -- Roles & Permissions
            ('VIEW_ROLES', 'Can view roles', 1, '$now'),
            ('MANAGE_ROLES', 'Can manage roles', 1, '$now'),
            ('VIEW_PERMISSIONS', 'Can view permissions', 1, '$now'),
            ('MANAGE_PERMISSIONS', 'Can manage permissions', 1, '$now'),

            -- Articles (for future)
            ('VIEW_ARTICLES', 'Can view articles', 1, '$now'),
            ('CREATE_ARTICLE', 'Can create articles', 1, '$now'),
            ('EDIT_ARTICLE', 'Can edit articles', 1, '$now'),
            ('DELETE_ARTICLE', 'Can delete articles', 1, '$now')
        ");

        // Role hierarchy: SUPER_ADMIN > ADMIN > MODERATOR > STAFF > USER
        $this->addSql("INSERT INTO veloce_roles_hierarchy (parent_role_id, child_role_id, created_at) VALUES
            (1, 2, '$now'),  -- SUPER_ADMIN inherits ADMIN
            (2, 3, '$now'),  -- ADMIN inherits MODERATOR
            (3, 4, '$now'),  -- MODERATOR inherits STAFF
            (4, 5, '$now')   -- STAFF inherits USER
        ");

        // Assign permissions to roles
        $this->addSql("INSERT INTO veloce_roles_permission (role_id, permission_id, created_at) VALUES
            -- SUPER_ADMIN gets everything via hierarchy, but also exclusive permissions
            (1, 8, '$now'),   -- MANAGE_ROLES
            (1, 10, '$now'),  -- MANAGE_PERMISSIONS

            -- ADMIN
            (2, 2, '$now'),   -- MANAGE_SETTINGS
            (2, 4, '$now'),   -- MANAGE_USERS
            (2, 6, '$now'),   -- MANAGE_BANS
            (2, 14, '$now'),  -- DELETE_ARTICLE

            -- MODERATOR
            (3, 3, '$now'),   -- VIEW_USERS
            (3, 5, '$now'),   -- VIEW_BANS
            (3, 7, '$now'),   -- VIEW_ROLES
            (3, 9, '$now'),   -- VIEW_PERMISSIONS
            (3, 12, '$now'),  -- CREATE_ARTICLE
            (3, 13, '$now'),  -- EDIT_ARTICLE

            -- STAFF
            (4, 1, '$now'),   -- VIEW_SETTINGS
            (4, 11, '$now'),  -- VIEW_ARTICLES

            -- USER (basic permissions only)
            (5, 11, '$now')   -- VIEW_ARTICLES
        ");

        // Assign roles to ranks (user.rank field)
        // Rank 7 = Admin, Rank 6 = Mod, etc.
        $this->addSql("INSERT INTO veloce_roles_rank (role_id, rank_id, created_at) VALUES
            (1, 7, '$now'),  -- Rank 7 = SUPER_ADMIN
            (2, 6, '$now'),  -- Rank 6 = ADMIN
            (3, 5, '$now'),  -- Rank 5 = MODERATOR
            (3, 4, '$now'),  -- Rank 4 = MODERATOR
            (4, 3, '$now'),  -- Rank 3 = STAFF
            (4, 2, '$now'),  -- Rank 2 = STAFF
            (5, 1, '$now')   -- Rank 1 = USER
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM veloce_roles_rank');
        $this->addSql('DELETE FROM veloce_roles_permission');
        $this->addSql('DELETE FROM veloce_roles_hierarchy');
        $this->addSql('DELETE FROM veloce_permissions');
        $this->addSql('DELETE FROM veloce_roles');
    }
}
