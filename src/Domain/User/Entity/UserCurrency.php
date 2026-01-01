<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity]
#[ORM\Table(name: 'users_currency')]
class UserCurrency
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'currencies')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Ignore]
    private User $user {
        get => $this->user;
    }

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[Groups(['currency:read'])]
    public private(set) int $type {
        get => $this->type;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['currency:read'])]
    public private(set) int $amount = 0 {
        get => $this->amount;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setType(int $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function addAmount(int $amount): static
    {
        $this->amount += $amount;
        return $this;
    }

    public function subtractAmount(int $amount): static
    {
        $this->amount = max(0, $this->amount - $amount);
        return $this;
    }
}
