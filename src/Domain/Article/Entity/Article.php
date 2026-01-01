<?php

declare(strict_types=1);

namespace App\Domain\Article\Entity;

use App\Domain\Article\Enum\ArticleStatus;
use App\Domain\Article\Repository\ArticleRepository;
use App\Domain\Shared\Entity\TimestampableTrait;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'veloce_articles')]
#[ORM\Index(columns: ['status'], name: 'idx_article_status')]
#[ORM\Index(columns: ['published_at'], name: 'idx_article_published')]
#[ORM\Index(columns: ['is_pinned'], name: 'idx_article_pinned')]
#[ORM\Index(columns: ['is_featured'], name: 'idx_article_featured')]
#[ORM\HasLifecycleCallbacks]
class Article
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(length: 255)]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    public private(set) string $title {
        get => $this->title;
        set => trim($value);
    }

    #[ORM\Column(length: 280, unique: true)]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    public private(set) string $slug {
        get => $this->slug;
        set => strtolower(trim($value));
    }

    #[ORM\Column(type: 'text')]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    public private(set) string $description {
        get => $this->description;
        set => trim($value);
    }

    #[ORM\Column(type: 'text')]
    #[Groups(['article:read', 'article:admin'])]
    public private(set) string $content {
        get => $this->content;
    }

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    public private(set) ?string $image = null {
        get => $this->image;
    }

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    public private(set) ?string $thumbnail = null {
        get => $this->thumbnail;
    }

    #[ORM\Column(length: 20, enumType: ArticleStatus::class)]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    public private(set) ArticleStatus $status = ArticleStatus::DRAFT {
        get => $this->status;
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    public private(set) ?\DateTimeImmutable $publishedAt = null {
        get => $this->publishedAt;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    public private(set) bool $isPinned = false {
        get => $this->isPinned;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['article:read', 'article:list', 'article:admin'])]
    public private(set) bool $isFeatured = false {
        get => $this->isFeatured;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['article:read', 'article:admin'])]
    public private(set) int $viewCount = 0 {
        get => $this->viewCount;
    }

    #[ORM\Column(length: 160, nullable: true)]
    #[Groups(['article:read', 'article:admin'])]
    public private(set) ?string $metaTitle = null {
        get => $this->metaTitle;
    }

    #[ORM\Column(length: 320, nullable: true)]
    #[Groups(['article:read', 'article:admin'])]
    public private(set) ?string $metaDescription = null {
        get => $this->metaDescription;
    }

    #[ORM\Column(name: 'author_id', type: 'integer')]
    #[Groups(['article:admin'])]
    public private(set) int $authorId {
        get => $this->authorId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['article:read', 'article:list'])]
    private ?User $author = null {
        get => $this->author;
    }

    #[ORM\Column(name: 'category_id', type: 'integer', nullable: true)]
    #[Ignore]
    public private(set) ?int $categoryId = null {
        get => $this->categoryId;
    }

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['article:read', 'article:list'])]
    private ?Category $category = null {
        get => $this->category;
    }

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'veloce_article_tag')]
    #[Groups(['article:read'])]
    private Collection $tags {
        get => $this->tags;
    }

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'article', cascade: ['remove'])]
    #[Ignore]
    private Collection $comments {
        get => $this->comments;
    }

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    #[Groups(['article:read', 'article:list'])]
    public function getReadingTime(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        return max(1, (int) ceil($wordCount / 200)); // 200 words per minute
    }

    #[Groups(['article:read', 'article:list'])]
    public function getCommentCount(): int
    {
        return $this->comments->filter(
            fn(Comment $c) => $c->status->isVisible()
        )->count();
    }

    public function updateTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function updateSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function generateSlug(): static
    {
        $this->slug = (new AsciiSlugger())->slug($this->title)->lower()->toString();
        return $this;
    }

    public function updateDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function updateContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function updateImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function updateThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function updateCategory(?Category $category): static
    {
        $this->category = $category;
        $this->categoryId = $category?->getId();
        return $this;
    }

    public function updateMeta(?string $title, ?string $description): static
    {
        $this->metaTitle = $title;
        $this->metaDescription = $description;
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

    public function feature(): static
    {
        $this->isFeatured = true;
        return $this;
    }

    public function unfeature(): static
    {
        $this->isFeatured = false;
        return $this;
    }

    public function incrementViewCount(): static
    {
        $this->viewCount++;
        return $this;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->incrementUsage();
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            $tag->decrementUsage();
        }
        return $this;
    }

    public function clearTags(): static
    {
        foreach ($this->tags as $tag) {
            $tag->decrementUsage();
        }
        $this->tags->clear();
        return $this;
    }

    // Status transitions
    public function publish(): static
    {
        $this->status = ArticleStatus::PUBLISHED;
        $this->publishedAt = new \DateTimeImmutable();
        return $this;
    }

    public function schedule(\DateTimeImmutable $publishAt): static
    {
        $this->status = ArticleStatus::SCHEDULED;
        $this->publishedAt = $publishAt;
        return $this;
    }

    public function archive(): static
    {
        $this->status = ArticleStatus::ARCHIVED;
        return $this;
    }

    public function toDraft(): static
    {
        $this->status = ArticleStatus::DRAFT;
        $this->publishedAt = null;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::PUBLISHED;
    }

    public function isScheduled(): bool
    {
        return $this->status === ArticleStatus::SCHEDULED;
    }

    public function shouldPublishNow(): bool
    {
        return $this->isScheduled()
            && $this->publishedAt !== null
            && $this->publishedAt <= new \DateTimeImmutable();
    }

    public static function create(
        string $title,
        string $description,
        string $content,
        int $authorId,
    ): self {
        $article = new self();
        $article->title = $title;
        $article->slug = (new AsciiSlugger())->slug($title)->lower()->toString();
        $article->description = $description;
        $article->content = $content;
        $article->authorId = $authorId;

        return $article;
    }
}
