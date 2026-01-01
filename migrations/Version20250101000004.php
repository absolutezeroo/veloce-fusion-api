<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Article module - Seeds article management permissions.
 */
final class Version20250101000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed article management permissions and assign to roles';
    }

    public function up(Schema $schema): void
    {
        $now = date('Y-m-d H:i:s');

        // Insert article permissions
        $this->addSql("
            INSERT INTO veloce_permissions (name, description, status, created_at) VALUES
            ('VIEW_ARTICLES', 'Can view all articles including drafts', 1, '$now'),
            ('CREATE_ARTICLE', 'Can create new articles', 1, '$now'),
            ('EDIT_ARTICLE', 'Can edit existing articles', 1, '$now'),
            ('PUBLISH_ARTICLE', 'Can publish, schedule, and archive articles', 1, '$now'),
            ('DELETE_ARTICLE', 'Can delete articles', 1, '$now'),
            ('MANAGE_CATEGORIES', 'Can manage article categories', 1, '$now'),
            ('MODERATE_COMMENTS', 'Can moderate article comments', 1, '$now')
            ON DUPLICATE KEY UPDATE description = VALUES(description)
        ");

        // Assign article permissions to roles
        // Staff (rank 3+): VIEW, CREATE, EDIT
        $this->addSql("
            INSERT IGNORE INTO veloce_roles_permission (role_id, permission_id, created_at)
            SELECT r.id, p.id, '$now'
            FROM veloce_roles r
            CROSS JOIN veloce_permissions p
            WHERE r.name = 'STAFF'
            AND p.name IN ('VIEW_ARTICLES', 'CREATE_ARTICLE', 'EDIT_ARTICLE')
        ");

        // Moderator (rank 4+): + PUBLISH, MODERATE_COMMENTS (inherited via hierarchy)
        $this->addSql("
            INSERT IGNORE INTO veloce_roles_permission (role_id, permission_id, created_at)
            SELECT r.id, p.id, '$now'
            FROM veloce_roles r
            CROSS JOIN veloce_permissions p
            WHERE r.name = 'MODERATOR'
            AND p.name IN ('PUBLISH_ARTICLE', 'MODERATE_COMMENTS')
        ");

        // Admin (rank 5+): + DELETE, MANAGE_CATEGORIES (inherited via hierarchy)
        $this->addSql("
            INSERT IGNORE INTO veloce_roles_permission (role_id, permission_id, created_at)
            SELECT r.id, p.id, '$now'
            FROM veloce_roles r
            CROSS JOIN veloce_permissions p
            WHERE r.name = 'ADMIN'
            AND p.name IN ('DELETE_ARTICLE', 'MANAGE_CATEGORIES')
        ");
    }

    public function down(Schema $schema): void
    {
        // Remove article role-permission assignments
        $this->addSql("
            DELETE rp FROM veloce_roles_permission rp
            INNER JOIN veloce_permissions p ON rp.permission_id = p.id
            WHERE p.name IN ('VIEW_ARTICLES', 'CREATE_ARTICLE', 'EDIT_ARTICLE', 'PUBLISH_ARTICLE', 'DELETE_ARTICLE', 'MANAGE_CATEGORIES', 'MODERATE_COMMENTS')
        ");

        // Remove article permissions
        $this->addSql("
            DELETE FROM veloce_permissions
            WHERE name IN ('VIEW_ARTICLES', 'CREATE_ARTICLE', 'EDIT_ARTICLE', 'PUBLISH_ARTICLE', 'DELETE_ARTICLE', 'MANAGE_CATEGORIES', 'MODERATE_COMMENTS')
        ");
    }
}
