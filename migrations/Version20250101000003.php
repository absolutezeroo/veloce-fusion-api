<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Article module - Creates tables for articles, categories, tags, and comments.
 */
final class Version20250101000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create article management tables (categories, tags, articles, article_tag, comments)';
    }

    public function up(Schema $schema): void
    {
        // Categories table
        $this->addSql('
            CREATE TABLE veloce_article_categories (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                color VARCHAR(7) DEFAULT NULL,
                sort_order SMALLINT DEFAULT 0 NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_category_slug (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        // Tags table
        $this->addSql('
            CREATE TABLE veloce_article_tags (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(50) NOT NULL,
                slug VARCHAR(60) NOT NULL,
                usage_count INT DEFAULT 0 NOT NULL,
                UNIQUE INDEX UNIQ_tag_slug (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        // Articles table
        $this->addSql('
            CREATE TABLE veloce_articles (
                id INT AUTO_INCREMENT NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(280) NOT NULL,
                description LONGTEXT NOT NULL,
                content LONGTEXT NOT NULL,
                image VARCHAR(500) DEFAULT NULL,
                thumbnail VARCHAR(500) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "draft",
                published_at DATETIME DEFAULT NULL,
                is_pinned TINYINT(1) DEFAULT 0 NOT NULL,
                is_featured TINYINT(1) DEFAULT 0 NOT NULL,
                view_count INT DEFAULT 0 NOT NULL,
                meta_title VARCHAR(160) DEFAULT NULL,
                meta_description VARCHAR(320) DEFAULT NULL,
                author_id INT NOT NULL,
                category_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_article_slug (slug),
                INDEX IDX_article_author (author_id),
                INDEX IDX_article_category (category_id),
                INDEX IDX_article_status (status),
                INDEX IDX_article_published (published_at),
                INDEX IDX_article_pinned (is_pinned),
                INDEX IDX_article_featured (is_featured),
                PRIMARY KEY(id),
                CONSTRAINT FK_article_author FOREIGN KEY (author_id) REFERENCES users (id),
                CONSTRAINT FK_article_category FOREIGN KEY (category_id) REFERENCES veloce_article_categories (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        // Article-Tag pivot table (many-to-many)
        $this->addSql('
            CREATE TABLE veloce_article_tag (
                article_id INT NOT NULL,
                tag_id INT NOT NULL,
                INDEX IDX_article_tag_article (article_id),
                INDEX IDX_article_tag_tag (tag_id),
                PRIMARY KEY(article_id, tag_id),
                CONSTRAINT FK_article_tag_article FOREIGN KEY (article_id) REFERENCES veloce_articles (id) ON DELETE CASCADE,
                CONSTRAINT FK_article_tag_tag FOREIGN KEY (tag_id) REFERENCES veloce_article_tags (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');

        // Comments table
        $this->addSql('
            CREATE TABLE veloce_article_comments (
                id INT AUTO_INCREMENT NOT NULL,
                content LONGTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                is_edited TINYINT(1) DEFAULT 0 NOT NULL,
                article_id INT NOT NULL,
                user_id INT NOT NULL,
                parent_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                INDEX IDX_comment_article (article_id),
                INDEX IDX_comment_user (user_id),
                INDEX IDX_comment_status (status),
                INDEX IDX_comment_parent (parent_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_comment_article FOREIGN KEY (article_id) REFERENCES veloce_articles (id) ON DELETE CASCADE,
                CONSTRAINT FK_comment_user FOREIGN KEY (user_id) REFERENCES users (id),
                CONSTRAINT FK_comment_parent FOREIGN KEY (parent_id) REFERENCES veloce_article_comments (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS veloce_article_comments');
        $this->addSql('DROP TABLE IF EXISTS veloce_article_tag');
        $this->addSql('DROP TABLE IF EXISTS veloce_articles');
        $this->addSql('DROP TABLE IF EXISTS veloce_article_tags');
        $this->addSql('DROP TABLE IF EXISTS veloce_article_categories');
    }
}
