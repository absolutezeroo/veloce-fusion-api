<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Forum module migration - Creates forum categories, threads, posts and votes tables.
 */
final class Version20260101204009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Forum module tables (categories, threads, posts) and votes table';
    }

    public function up(Schema $schema): void
    {
        // Forum Categories
        $this->addSql('CREATE TABLE veloce_forum_categories (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            icon VARCHAR(50) DEFAULT NULL,
            parent_id INT DEFAULT NULL,
            position INT DEFAULT 0 NOT NULL,
            is_locked TINYINT(1) DEFAULT 0 NOT NULL,
            thread_count INT DEFAULT 0 NOT NULL,
            post_count INT DEFAULT 0 NOT NULL,
            last_thread_id INT DEFAULT NULL,
            last_post_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            UNIQUE INDEX idx_forum_category_slug (slug),
            INDEX idx_forum_category_parent (parent_id),
            INDEX idx_forum_category_position (position),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Forum Threads
        $this->addSql('CREATE TABLE veloce_forum_threads (
            id INT AUTO_INCREMENT NOT NULL,
            category_id INT NOT NULL,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            slug VARCHAR(220) NOT NULL,
            content LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "open",
            is_pinned TINYINT(1) DEFAULT 0 NOT NULL,
            is_hot TINYINT(1) DEFAULT 0 NOT NULL,
            view_count INT DEFAULT 0 NOT NULL,
            reply_count INT DEFAULT 0 NOT NULL,
            last_post_id INT DEFAULT NULL,
            last_post_user_id INT DEFAULT NULL,
            last_post_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            INDEX idx_forum_thread_category (category_id),
            INDEX idx_forum_thread_user (user_id),
            INDEX idx_forum_thread_slug (slug),
            INDEX idx_forum_thread_status (status),
            INDEX idx_forum_thread_pinned (is_pinned),
            INDEX idx_forum_thread_last_post (last_post_at),
            INDEX idx_forum_thread_last_post_user (last_post_user_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Forum Posts
        $this->addSql('CREATE TABLE veloce_forum_posts (
            id INT AUTO_INCREMENT NOT NULL,
            thread_id INT NOT NULL,
            user_id INT NOT NULL,
            content LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "approved",
            is_edited TINYINT(1) DEFAULT 0 NOT NULL,
            edited_at DATETIME DEFAULT NULL,
            quoted_post_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            INDEX idx_forum_post_thread (thread_id),
            INDEX idx_forum_post_user (user_id),
            INDEX idx_forum_post_status (status),
            INDEX idx_forum_post_quoted (quoted_post_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Votes table (for threads and posts)
        $this->addSql('CREATE TABLE veloce_votes (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            entity_id INT NOT NULL,
            vote_entity INT NOT NULL COMMENT "1=article, 2=comment, 3=forum_thread, 4=forum_post",
            vote_type INT NOT NULL COMMENT "1=like, 2=dislike",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            INDEX idx_vote_user (user_id),
            INDEX idx_vote_entity (entity_id, vote_entity),
            UNIQUE INDEX unique_user_vote (user_id, entity_id, vote_entity),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Foreign Keys
        $this->addSql('ALTER TABLE veloce_forum_categories ADD CONSTRAINT fk_forum_category_parent FOREIGN KEY (parent_id) REFERENCES veloce_forum_categories (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE veloce_forum_threads ADD CONSTRAINT fk_forum_thread_category FOREIGN KEY (category_id) REFERENCES veloce_forum_categories (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE veloce_forum_threads ADD CONSTRAINT fk_forum_thread_user FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE veloce_forum_threads ADD CONSTRAINT fk_forum_thread_last_post_user FOREIGN KEY (last_post_user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE veloce_forum_posts ADD CONSTRAINT fk_forum_post_thread FOREIGN KEY (thread_id) REFERENCES veloce_forum_threads (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE veloce_forum_posts ADD CONSTRAINT fk_forum_post_user FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE veloce_forum_posts ADD CONSTRAINT fk_forum_post_quoted FOREIGN KEY (quoted_post_id) REFERENCES veloce_forum_posts (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE veloce_votes ADD CONSTRAINT fk_vote_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE veloce_forum_posts DROP FOREIGN KEY fk_forum_post_thread');
        $this->addSql('ALTER TABLE veloce_forum_posts DROP FOREIGN KEY fk_forum_post_user');
        $this->addSql('ALTER TABLE veloce_forum_posts DROP FOREIGN KEY fk_forum_post_quoted');
        $this->addSql('ALTER TABLE veloce_forum_threads DROP FOREIGN KEY fk_forum_thread_category');
        $this->addSql('ALTER TABLE veloce_forum_threads DROP FOREIGN KEY fk_forum_thread_user');
        $this->addSql('ALTER TABLE veloce_forum_threads DROP FOREIGN KEY fk_forum_thread_last_post_user');
        $this->addSql('ALTER TABLE veloce_forum_categories DROP FOREIGN KEY fk_forum_category_parent');
        $this->addSql('ALTER TABLE veloce_votes DROP FOREIGN KEY fk_vote_user');

        $this->addSql('DROP TABLE veloce_forum_posts');
        $this->addSql('DROP TABLE veloce_forum_threads');
        $this->addSql('DROP TABLE veloce_forum_categories');
        $this->addSql('DROP TABLE veloce_votes');
    }
}
