<?php

declare(strict_types=1);

namespace App\Domain\Setting\Entity;

use App\Domain\Setting\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: 'veloce_settings')]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['setting:read', 'setting:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: '`key`', length: 255, unique: true)]
    #[Groups(['setting:read', 'setting:list'])]
    public private(set) string $key {
        get => $this->key;
        set => trim($value);
    }

    #[ORM\Column(type: 'text')]
    #[Groups(['setting:read', 'setting:list'])]
    public private(set) string $value {
        get => $this->value;
        set => $value;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function updateValue(string $value): static
    {
        $this->value = $value;
        return $this;
    }
}
