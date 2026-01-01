<?php

declare(strict_types=1);

namespace App\Domain\Article\Entity;

use App\Domain\Article\Repository\CategoryRepository;
use App\Domain\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'veloce_article_categories')]
#[ORM\HasLifecycleCallbacks]
class Category
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['category:read', 'category:list', 'article:read', 'article:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(length: 100)]
    #[Groups(['category:read', 'category:list', 'article:read', 'article:list'])]
    public private(set) string $name {
        get => $this->name;
        set => trim($value);
    }

    #[ORM\Column(length: 120, unique: true)]
    #[Groups(['category:read', 'category:list', 'article:read', 'article:list'])]
    public private(set) string $slug {
        get => $this->slug;
        set => strtolower(trim($value));
    }

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['category:read'])]
    public private(set) ?string $description = null {
        get => $this->description;
    }

    #[ORM\Column(length: 7, nullable: true)]
    #[Groups(['category:read', 'category:list'])]
    public private(set) ?string $color = null {
        get => $this->color;
    }

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    #[Groups(['category:read'])]
    public private(set) int $sortOrder = 0 {
        get => $this->sortOrder;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['category:read', 'category:list'])]
    public private(set) bool $isActive = true {
        get => $this->isActive;
    }

    /** @var Collection<int, Article> */
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'category')]
    #[Ignore]
    private Collection $articles {
        get => $this->articles;
    }

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
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

    public function updateColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function updateSortOrder(int $order): static
    {
        $this->sortOrder = $order;
        return $this;
    }

    public function activate(): static
    {
        $this->isActive = true;
        return $this;
    }

    public function deactivate(): static
    {
        $this->isActive = false;
        return $this;
    }

    public function getArticleCount(): int
    {
        return $this->articles->count();
    }

    public static function create(string $name, ?string $description = null, ?string $color = null): self
    {
        $category = new self();
        $category->name = $name;
        $category->slug = (new AsciiSlugger())->slug($name)->lower()->toString();
        $category->description = $description;
        $category->color = $color;

        return $category;
    }
}
