<?php

declare(strict_types=1);

namespace App\Domain\Forum\Entity;

use App\Domain\Forum\Repository\ForumCategoryRepository;
use App\Domain\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: ForumCategoryRepository::class)]
#[ORM\Table(name: 'veloce_forum_categories')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_forum_category_parent')]
#[ORM\Index(columns: ['slug'], name: 'idx_forum_category_slug')]
#[ORM\Index(columns: ['position'], name: 'idx_forum_category_position')]
#[ORM\HasLifecycleCallbacks]
class ForumCategory
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['forum_category:read', 'forum_category:list', 'forum_thread:read'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(length: 100)]
    #[Groups(['forum_category:read', 'forum_category:list', 'forum_thread:read'])]
    public private(set) string $name {
        get => $this->name;
        set => trim($value);
    }

    #[ORM\Column(length: 120, unique: true)]
    #[Groups(['forum_category:read', 'forum_category:list', 'forum_thread:read'])]
    public private(set) string $slug {
        get => $this->slug;
        set => strtolower(trim($value));
    }

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['forum_category:read', 'forum_category:list'])]
    public private(set) ?string $description = null {
        get => $this->description;
        set => $value !== null ? trim($value) : null;
    }

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['forum_category:read', 'forum_category:list'])]
    public private(set) ?string $icon = null {
        get => $this->icon;
    }

    #[ORM\Column(name: 'parent_id', type: 'integer', nullable: true)]
    #[Groups(['forum_category:read'])]
    public private(set) ?int $parentId = null {
        get => $this->parentId;
    }

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Ignore]
    private ?self $parent = null {
        get => $this->parent;
    }

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['forum_category:read'])]
    private Collection $children {
        get => $this->children;
    }

    /** @var Collection<int, ForumThread> */
    #[ORM\OneToMany(targetEntity: ForumThread::class, mappedBy: 'category')]
    #[Ignore]
    private Collection $threads {
        get => $this->threads;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['forum_category:read', 'forum_category:list'])]
    public private(set) int $position = 0 {
        get => $this->position;
    }

    #[ORM\Column(name: 'is_locked', type: 'boolean', options: ['default' => false])]
    #[Groups(['forum_category:read', 'forum_category:list'])]
    public private(set) bool $isLocked = false {
        get => $this->isLocked;
    }

    #[ORM\Column(name: 'thread_count', type: 'integer', options: ['default' => 0])]
    #[Groups(['forum_category:read', 'forum_category:list'])]
    public private(set) int $threadCount = 0 {
        get => $this->threadCount;
    }

    #[ORM\Column(name: 'post_count', type: 'integer', options: ['default' => 0])]
    #[Groups(['forum_category:read', 'forum_category:list'])]
    public private(set) int $postCount = 0 {
        get => $this->postCount;
    }

    #[ORM\Column(name: 'last_thread_id', type: 'integer', nullable: true)]
    #[Groups(['forum_category:read'])]
    public private(set) ?int $lastThreadId = null {
        get => $this->lastThreadId;
    }

    #[ORM\Column(name: 'last_post_at', type: 'datetime_immutable', nullable: true)]
    #[Groups(['forum_category:read', 'forum_category:list'])]
    public private(set) ?\DateTimeImmutable $lastPostAt = null {
        get => $this->lastPostAt;
    }

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->threads = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return Collection<int, ForumThread>
     */
    public function getThreads(): Collection
    {
        return $this->threads;
    }

    #[Groups(['forum_category:read', 'forum_category:list'])]
    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    #[Groups(['forum_category:read', 'forum_category:list'])]
    public function isRoot(): bool
    {
        return $this->parentId === null;
    }

    public function updateName(string $name): static
    {
        $this->name = $name;
        $this->slug = (new AsciiSlugger())->slug($name)->lower()->toString();
        return $this;
    }

    public function updateDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function updateIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        $this->parentId = $parent?->getId();
        return $this;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function lock(): static
    {
        $this->isLocked = true;
        return $this;
    }

    public function unlock(): static
    {
        $this->isLocked = false;
        return $this;
    }

    public function incrementThreadCount(): static
    {
        $this->threadCount++;
        return $this;
    }

    public function decrementThreadCount(): static
    {
        $this->threadCount = max(0, $this->threadCount - 1);
        return $this;
    }

    public function incrementPostCount(): static
    {
        $this->postCount++;
        return $this;
    }

    public function decrementPostCount(): static
    {
        $this->postCount = max(0, $this->postCount - 1);
        return $this;
    }

    public function updateLastActivity(?int $threadId, ?\DateTimeImmutable $postAt): static
    {
        $this->lastThreadId = $threadId;
        $this->lastPostAt = $postAt;
        return $this;
    }

    public static function create(
        string $name,
        ?string $description = null,
        ?string $icon = null,
        ?self $parent = null,
        int $position = 0,
    ): self {
        $category = new self();
        $category->name = $name;
        $category->slug = (new AsciiSlugger())->slug($name)->lower()->toString();
        $category->description = $description;
        $category->icon = $icon;
        $category->setParent($parent);
        $category->position = $position;

        return $category;
    }
}
