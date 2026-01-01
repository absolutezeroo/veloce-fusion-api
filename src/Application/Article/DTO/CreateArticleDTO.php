<?php

declare(strict_types=1);

namespace App\Application\Article\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateArticleDTO
{
    /**
     * @param string[]|null $tags
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 255)]
        public string $title,

        #[Assert\NotBlank]
        #[Assert\Length(min: 10, max: 500)]
        public string $description,

        #[Assert\NotBlank]
        #[Assert\Length(min: 50)]
        public string $content,

        #[Assert\Length(max: 500)]
        public ?string $image = null,

        #[Assert\Length(max: 500)]
        public ?string $thumbnail = null,

        #[Assert\Positive]
        public ?int $categoryId = null,

        #[Assert\All([
            new Assert\Length(min: 2, max: 50),
        ])]
        public ?array $tags = null,

        #[Assert\Length(max: 160)]
        public ?string $metaTitle = null,

        #[Assert\Length(max: 320)]
        public ?string $metaDescription = null,

        public bool $isPinned = false,

        public bool $isFeatured = false,
    ) {}
}
