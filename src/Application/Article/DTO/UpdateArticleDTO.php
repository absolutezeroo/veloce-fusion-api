<?php

declare(strict_types=1);

namespace App\Application\Article\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateArticleDTO
{
    /**
     * @param string[]|null $tags
     */
    public function __construct(
        #[Assert\Length(min: 5, max: 255)]
        public ?string $title = null,

        #[Assert\Length(min: 10, max: 500)]
        public ?string $description = null,

        #[Assert\Length(min: 50)]
        public ?string $content = null,

        #[Assert\Length(max: 500)]
        public ?string $image = null,

        #[Assert\Length(max: 500)]
        public ?string $thumbnail = null,

        public ?int $categoryId = null,

        #[Assert\All([
            new Assert\Length(min: 2, max: 50),
        ])]
        public ?array $tags = null,

        #[Assert\Length(max: 160)]
        public ?string $metaTitle = null,

        #[Assert\Length(max: 320)]
        public ?string $metaDescription = null,

        public ?bool $isPinned = null,

        public ?bool $isFeatured = null,
    ) {}
}
