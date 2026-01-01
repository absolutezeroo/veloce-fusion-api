<?php

declare(strict_types=1);

namespace App\Domain\Forum\Entity;

use App\Domain\Forum\Enum\PostStatus;
use App\Domain\Forum\Repository\ForumPostRepository;
use App\Domain\Shared\Entity\TimestampableTrait;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
#[ORM\Table(name: 'veloce_forum_posts')]
#[ORM\Index(columns: ['thread_id'], name: 'idx_forum_post_thread')]
#[ORM\Index(columns: ['user_id'], name: 'idx_forum_post_user')]
#[ORM\Index(columns: ['status'], name: 'idx_forum_post_status')]
#[ORM\Index(columns: ['quoted_post_id'], name: 'idx_forum_post_quoted')]
#[ORM\HasLifecycleCallbacks]
class ForumPost
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['forum_post:read', 'forum_post:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'thread_id', type: 'integer')]
    #[Ignore]
    public private(set) int $threadId {
        get => $this->threadId;
    }

    #[ORM\ManyToOne(targetEntity: ForumThread::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'thread_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['forum_post:read'])]
    private ?ForumThread $thread = null {
        get => $this->thread;
    }

    #[ORM\Column(name: 'user_id', type: 'integer')]
    #[Ignore]
    public private(set) int $userId {
        get => $this->userId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['forum_post:read', 'forum_post:list'])]
    private ?User $user = null {
        get => $this->user;
    }

    #[ORM\Column(type: 'text')]
    #[Groups(['forum_post:read', 'forum_post:list'])]
    public private(set) string $content {
        get => $this->content;
        set => trim($value);
    }

    #[ORM\Column(length: 20, enumType: PostStatus::class)]
    #[Groups(['forum_post:read', 'forum_post:list', 'forum_post:admin'])]
    public private(set) PostStatus $status = PostStatus::APPROVED {
        get => $this->status;
    }

    #[ORM\Column(name: 'is_edited', type: 'boolean', options: ['default' => false])]
    #[Groups(['forum_post:read', 'forum_post:list'])]
    public private(set) bool $isEdited = false {
        get => $this->isEdited;
    }

    #[ORM\Column(name: 'edited_at', type: 'datetime_immutable', nullable: true)]
    #[Groups(['forum_post:read'])]
    public private(set) ?\DateTimeImmutable $editedAt = null {
        get => $this->editedAt;
    }

    #[ORM\Column(name: 'quoted_post_id', type: 'integer', nullable: true)]
    #[Groups(['forum_post:read', 'forum_post:list'])]
    public private(set) ?int $quotedPostId = null {
        get => $this->quotedPostId;
    }

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'quoted_post_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['forum_post:read'])]
    private ?self $quotedPost = null {
        get => $this->quotedPost;
    }

    // Vote counts (computed, not stored)
    private int $likes = 0;
    private int $dislikes = 0;

    public function getId(): int
    {
        return $this->id;
    }

    public function getThread(): ?ForumThread
    {
        return $this->thread;
    }

    public function setThread(ForumThread $thread): static
    {
        $this->thread = $thread;
        $this->threadId = $thread->getId();
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getQuotedPost(): ?self
    {
        return $this->quotedPost;
    }

    #[Groups(['forum_post:read', 'forum_post:list'])]
    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = $likes;
        return $this;
    }

    #[Groups(['forum_post:read', 'forum_post:list'])]
    public function getDislikes(): int
    {
        return $this->dislikes;
    }

    public function setDislikes(int $dislikes): static
    {
        $this->dislikes = $dislikes;
        return $this;
    }

    public function updateContent(string $content): static
    {
        $this->content = $content;
        $this->isEdited = true;
        $this->editedAt = new \DateTimeImmutable();
        return $this;
    }

    public function approve(): static
    {
        $this->status = PostStatus::APPROVED;
        return $this;
    }

    public function hide(): static
    {
        $this->status = PostStatus::HIDDEN;
        return $this;
    }

    public function setPending(): static
    {
        $this->status = PostStatus::PENDING;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->status === PostStatus::APPROVED;
    }

    public function isVisible(): bool
    {
        return $this->status->isVisible();
    }

    public function setQuotedPost(?self $quotedPost): static
    {
        $this->quotedPost = $quotedPost;
        $this->quotedPostId = $quotedPost?->getId();
        return $this;
    }

    public static function create(
        int $threadId,
        int $userId,
        string $content,
        ?int $quotedPostId = null,
        bool $autoApprove = true,
    ): self {
        $post = new self();
        $post->threadId = $threadId;
        $post->userId = $userId;
        $post->content = $content;
        $post->quotedPostId = $quotedPostId;
        $post->status = $autoApprove ? PostStatus::APPROVED : PostStatus::PENDING;

        return $post;
    }
}
