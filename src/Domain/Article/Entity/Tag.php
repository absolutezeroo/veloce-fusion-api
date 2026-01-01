<?php

declare(strict_types=1);

namespace App\Domain\Article\Entity;

use App\Domain\Article\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'veloce_article_tags')]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['tag:read', 'tag:list', 'article:read'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(length: 50)]
    #[Groups(['tag:read', 'tag:list', 'article:read'])]
    public private(set) string $name {
        get => $this->name;
        set => trim($value);
    }

    #[ORM\Column(length: 60, unique: true)]
    #[Groups(['tag:read', 'tag:list', 'article:read'])]
    public private(set) string $slug {
        get => $this->slug;
        set => strtolower(trim($value));
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['tag:read', 'tag:list'])]
    public private(set) int $usageCount = 0 {
        get => $this->usageCount;
    }

    /** @var Collection<int, Article> */
    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
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

    public function incrementUsage(): static
    {
        $this->usageCount++;
        return $this;
    }

    public function decrementUsage(): static
    {
        if ($this->usageCount > 0) {
            $this->usageCount--;
        }
        return $this;
    }

    public function recalculateUsage(): static
    {
        $this->usageCount = $this->articles->count();
        return $this;
    }

    public static function create(string $name): self
    {
        $tag = new self();
        $tag->name = $name;
        $tag->slug = (new AsciiSlugger())->slug($name)->lower()->toString();

        return $tag;
    }
}
