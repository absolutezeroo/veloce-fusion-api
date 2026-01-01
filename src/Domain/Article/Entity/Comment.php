<?php

declare(strict_types=1);

namespace App\Domain\Article\Entity;

use App\Domain\Article\Enum\CommentStatus;
use App\Domain\Article\Repository\CommentRepository;
use App\Domain\Shared\Entity\TimestampableTrait;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'veloce_article_comments')]
#[ORM\Index(columns: ['article_id'], name: 'idx_comment_article')]
#[ORM\Index(columns: ['user_id'], name: 'idx_comment_user')]
#[ORM\Index(columns: ['status'], name: 'idx_comment_status')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_comment_parent')]
#[ORM\HasLifecycleCallbacks]
class Comment
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['comment:read', 'comment:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(type: 'text')]
    #[Groups(['comment:read', 'comment:list'])]
    public private(set) string $content {
        get => $this->content;
        set => trim($value);
    }

    #[ORM\Column(length: 20, enumType: CommentStatus::class)]
    #[Groups(['comment:read', 'comment:list', 'comment:admin'])]
    public private(set) CommentStatus $status = CommentStatus::PENDING {
        get => $this->status;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['comment:read', 'comment:list'])]
    public private(set) bool $isEdited = false {
        get => $this->isEdited;
    }

    #[ORM\Column(name: 'article_id', type: 'integer')]
    #[Ignore]
    public private(set) int $articleId {
        get => $this->articleId;
    }

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Ignore]
    private ?Article $article = null {
        get => $this->article;
    }

    #[ORM\Column(name: 'user_id', type: 'integer')]
    #[Ignore]
    public private(set) int $userId {
        get => $this->userId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['comment:read', 'comment:list'])]
    private ?User $user = null {
        get => $this->user;
    }

    #[ORM\Column(name: 'parent_id', type: 'integer', nullable: true)]
    #[Groups(['comment:read'])]
    public private(set) ?int $parentId = null {
        get => $this->parentId;
    }

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Ignore]
    private ?self $parent = null {
        get => $this->parent;
    }

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[Ignore]
    private Collection $replies {
        get => $this->replies;
    }

    // Vote counts (computed, not stored)
    private int $likes = 0;
    private int $dislikes = 0;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @return Collection<int, self>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    #[Groups(['comment:read', 'comment:list'])]
    public function getReplyCount(): int
    {
        return $this->replies->filter(
            fn(self $r) => $r->status->isVisible()
        )->count();
    }

    #[Groups(['comment:read', 'comment:list'])]
    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = $likes;
        return $this;
    }

    #[Groups(['comment:read', 'comment:list'])]
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
        return $this;
    }

    public function approve(): static
    {
        $this->status = CommentStatus::APPROVED;
        return $this;
    }

    public function reject(): static
    {
        $this->status = CommentStatus::REJECTED;
        return $this;
    }

    public function markAsSpam(): static
    {
        $this->status = CommentStatus::SPAM;
        return $this;
    }

    public function setPending(): static
    {
        $this->status = CommentStatus::PENDING;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->status === CommentStatus::APPROVED;
    }

    public function isReply(): bool
    {
        return $this->parentId !== null;
    }

    public static function create(
        int $articleId,
        int $userId,
        string $content,
        ?int $parentId = null,
        bool $autoApprove = false,
    ): self {
        $comment = new self();
        $comment->articleId = $articleId;
        $comment->userId = $userId;
        $comment->content = $content;
        $comment->parentId = $parentId;
        $comment->status = $autoApprove ? CommentStatus::APPROVED : CommentStatus::PENDING;

        return $comment;
    }
}
