<?php

declare(strict_types=1);

namespace App\Domain\Forum\Entity;

use App\Domain\Forum\Enum\ThreadStatus;
use App\Domain\Forum\Repository\ForumThreadRepository;
use App\Domain\Shared\Entity\TimestampableTrait;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: ForumThreadRepository::class)]
#[ORM\Table(name: 'veloce_forum_threads')]
#[ORM\Index(columns: ['category_id'], name: 'idx_forum_thread_category')]
#[ORM\Index(columns: ['user_id'], name: 'idx_forum_thread_user')]
#[ORM\Index(columns: ['slug'], name: 'idx_forum_thread_slug')]
#[ORM\Index(columns: ['status'], name: 'idx_forum_thread_status')]
#[ORM\Index(columns: ['is_pinned'], name: 'idx_forum_thread_pinned')]
#[ORM\Index(columns: ['last_post_at'], name: 'idx_forum_thread_last_post')]
#[ORM\HasLifecycleCallbacks]
class ForumThread
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['forum_thread:read', 'forum_thread:list', 'forum_post:read'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'category_id', type: 'integer')]
    #[Ignore]
    public private(set) int $categoryId {
        get => $this->categoryId;
    }

    #[ORM\ManyToOne(targetEntity: ForumCategory::class, inversedBy: 'threads')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['forum_thread:read'])]
    private ?ForumCategory $category = null {
        get => $this->category;
    }

    #[ORM\Column(name: 'user_id', type: 'integer')]
    #[Ignore]
    public private(set) int $userId {
        get => $this->userId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    private ?User $user = null {
        get => $this->user;
    }

    #[ORM\Column(length: 200)]
    #[Groups(['forum_thread:read', 'forum_thread:list', 'forum_post:read'])]
    public private(set) string $title {
        get => $this->title;
        set => trim($value);
    }

    #[ORM\Column(length: 220)]
    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    public private(set) string $slug {
        get => $this->slug;
        set => strtolower(trim($value));
    }

    #[ORM\Column(type: 'text')]
    #[Groups(['forum_thread:read'])]
    public private(set) string $content {
        get => $this->content;
        set => trim($value);
    }

    #[ORM\Column(length: 20, enumType: ThreadStatus::class)]
    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    public private(set) ThreadStatus $status = ThreadStatus::OPEN {
        get => $this->status;
    }

    #[ORM\Column(name: 'is_pinned', type: 'boolean', options: ['default' => false])]
    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    public private(set) bool $isPinned = false {
        get => $this->isPinned;
    }

    #[ORM\Column(name: 'is_hot', type: 'boolean', options: ['default' => false])]
    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    public private(set) bool $isHot = false {
        get => $this->isHot;
    }

    #[ORM\Column(name: 'view_count', type: 'integer', options: ['default' => 0])]
    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    public private(set) int $viewCount = 0 {
        get => $this->viewCount;
    }

    #[ORM\Column(name: 'reply_count', type: 'integer', options: ['default' => 0])]
    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    public private(set) int $replyCount = 0 {
        get => $this->replyCount;
    }

    #[ORM\Column(name: 'last_post_id', type: 'integer', nullable: true)]
    #[Groups(['forum_thread:read'])]
    public private(set) ?int $lastPostId = null {
        get => $this->lastPostId;
    }

    #[ORM\Column(name: 'last_post_user_id', type: 'integer', nullable: true)]
    #[Ignore]
    public private(set) ?int $lastPostUserId = null {
        get => $this->lastPostUserId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'last_post_user_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    private ?User $lastPostUser = null {
        get => $this->lastPostUser;
    }

    #[ORM\Column(name: 'last_post_at', type: 'datetime_immutable', nullable: true)]
    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    public private(set) ?\DateTimeImmutable $lastPostAt = null {
        get => $this->lastPostAt;
    }

    /** @var Collection<int, ForumPost> */
    #[ORM\OneToMany(targetEntity: ForumPost::class, mappedBy: 'thread', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    #[Ignore]
    private Collection $posts {
        get => $this->posts;
    }

    // Vote counts (computed, not stored)
    private int $likes = 0;
    private int $dislikes = 0;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCategory(): ?ForumCategory
    {
        return $this->category;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getLastPostUser(): ?User
    {
        return $this->lastPostUser;
    }

    public function setLastPostUser(?User $user): static
    {
        $this->lastPostUser = $user;
        $this->lastPostUserId = $user?->getId();
        return $this;
    }

    /**
     * @return Collection<int, ForumPost>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = $likes;
        return $this;
    }

    #[Groups(['forum_thread:read', 'forum_thread:list'])]
    public function getDislikes(): int
    {
        return $this->dislikes;
    }

    public function setDislikes(int $dislikes): static
    {
        $this->dislikes = $dislikes;
        return $this;
    }

    public function updateTitle(string $title): static
    {
        $this->title = $title;
        $this->slug = (new AsciiSlugger())->slug($title)->lower()->toString();
        return $this;
    }

    public function updateContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function open(): static
    {
        $this->status = ThreadStatus::OPEN;
        return $this;
    }

    public function close(): static
    {
        $this->status = ThreadStatus::CLOSED;
        return $this;
    }

    public function lock(): static
    {
        $this->status = ThreadStatus::LOCKED;
        return $this;
    }

    public function pin(): static
    {
        $this->isPinned = true;
        return $this;
    }

    public function unpin(): static
    {
        $this->isPinned = false;
        return $this;
    }

    public function markAsHot(): static
    {
        $this->isHot = true;
        return $this;
    }

    public function unmarkAsHot(): static
    {
        $this->isHot = false;
        return $this;
    }

    public function incrementViewCount(): static
    {
        $this->viewCount++;
        return $this;
    }

    public function incrementReplyCount(): static
    {
        $this->replyCount++;
        return $this;
    }

    public function decrementReplyCount(): static
    {
        $this->replyCount = max(0, $this->replyCount - 1);
        return $this;
    }

    public function updateLastPost(?int $postId, ?int $userId, ?\DateTimeImmutable $postAt): static
    {
        $this->lastPostId = $postId;
        $this->lastPostUserId = $userId;
        $this->lastPostAt = $postAt;
        return $this;
    }

    public function canReply(): bool
    {
        return $this->status->canReply();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function moveToCategory(ForumCategory $category): static
    {
        $this->category = $category;
        $this->categoryId = $category->getId();
        return $this;
    }

    public static function create(
        int $categoryId,
        int $userId,
        string $title,
        string $content,
    ): self {
        $thread = new self();
        $thread->categoryId = $categoryId;
        $thread->userId = $userId;
        $thread->title = $title;
        $thread->slug = (new AsciiSlugger())->slug($title)->lower()->toString();
        $thread->content = $content;
        $thread->status = ThreadStatus::OPEN;
        $thread->lastPostAt = new \DateTimeImmutable();
        $thread->lastPostUserId = $userId;

        return $thread;
    }
}
