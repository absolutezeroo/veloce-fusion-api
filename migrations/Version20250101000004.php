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
        // Insert article permissions
        $this->addSql("
            INSERT INTO veloce_permissions (name, description, category) VALUES
            ('VIEW_ARTICLES', 'Can view all articles including drafts', 'article'),
            ('CREATE_ARTICLE', 'Can create new articles', 'article'),
            ('EDIT_ARTICLE', 'Can edit existing articles', 'article'),
            ('PUBLISH_ARTICLE', 'Can publish, schedule, and archive articles', 'article'),
            ('DELETE_ARTICLE', 'Can delete articles', 'article'),
            ('MANAGE_CATEGORIES', 'Can manage article categories', 'article'),
            ('MODERATE_COMMENTS', 'Can moderate article comments', 'article')
        ");

        // Assign article permissions to roles
        // Staff (rank 3+): VIEW, CREATE, EDIT
        $this->addSql("
            INSERT INTO veloce_roles_permission (role_id, permission_id)
            SELECT r.id, p.id
            FROM veloce_roles r
            CROSS JOIN veloce_permissions p
            WHERE r.name = 'staff'
            AND p.name IN ('VIEW_ARTICLES', 'CREATE_ARTICLE', 'EDIT_ARTICLE')
        ");

        // Moderator (rank 4+): + PUBLISH, MODERATE_COMMENTS (inherited via hierarchy)
        $this->addSql("
            INSERT INTO veloce_roles_permission (role_id, permission_id)
            SELECT r.id, p.id
            FROM veloce_roles r
            CROSS JOIN veloce_permissions p
            WHERE r.name = 'moderator'
            AND p.name IN ('PUBLISH_ARTICLE', 'MODERATE_COMMENTS')
        ");

        // Admin (rank 5+): + DELETE, MANAGE_CATEGORIES (inherited via hierarchy)
        $this->addSql("
            INSERT INTO veloce_roles_permission (role_id, permission_id)
            SELECT r.id, p.id
            FROM veloce_roles r
            CROSS JOIN veloce_permissions p
            WHERE r.name = 'admin'
            AND p.name IN ('DELETE_ARTICLE', 'MANAGE_CATEGORIES')
        ");
    }

    public function down(Schema $schema): void
    {
        // Remove article role-permission assignments
        $this->addSql("
            DELETE rp FROM veloce_roles_permission rp
            INNER JOIN veloce_permissions p ON rp.permission_id = p.id
            WHERE p.category = 'article'
        ");

        // Remove article permissions
        $this->addSql("DELETE FROM veloce_permissions WHERE category = 'article'");
    }
}
