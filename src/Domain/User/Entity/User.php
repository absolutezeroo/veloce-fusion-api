<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\Shared\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User extends AbstractEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Column(length: 25, unique: true)]
    #[Groups(['user:read', 'user:list'])]
    public private(set) string $username {
        get => $this->username;
        set => strtolower(trim($value));
    }

    #[ORM\Column(length: 255)]
    #[Ignore]
    private string $password {
        get => $this->password;
    }

    #[ORM\Column(length: 255)]
    #[Groups(['user:read'])]
    public private(set) string $mail {
        get => $this->mail;
        set => strtolower(trim($value));
    }

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:list'])]
    public private(set) ?string $look = null {
        get => $this->look;
    }

    #[ORM\Column(length: 1, options: ['default' => 'M'])]
    #[Groups(['user:read'])]
    public private(set) string $gender = 'M' {
        get => $this->gender;
        set => strtoupper($value) === 'F' ? 'F' : 'M';
    }

    #[ORM\Column(length: 127, nullable: true)]
    #[Groups(['user:read', 'user:list'])]
    public private(set) ?string $motto = null {
        get => $this->motto;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['user:read'])]
    public private(set) int $credits = 0 {
        get => $this->credits;
    }

    #[ORM\Column(name: 'rank', type: 'integer', options: ['default' => 1])]
    #[Groups(['user:read'])]
    public private(set) int $rank = 1 {
        get => $this->rank;
    }

    #[ORM\Column(name: 'auth_ticket', length: 255, nullable: true)]
    #[Ignore]
    private ?string $authTicket = null {
        get => $this->authTicket;
    }

    #[ORM\Column(name: 'ip_register', length: 45)]
    #[Ignore]
    private string $ipRegister {
        get => $this->ipRegister;
    }

    #[ORM\Column(name: 'ip_current', length: 45, nullable: true)]
    #[Ignore]
    private ?string $ipCurrent = null {
        get => $this->ipCurrent;
    }

    #[ORM\Column(name: 'online', type: 'smallint', options: ['default' => 0])]
    #[Groups(['user:read', 'user:list'])]
    public private(set) int $online = 0 {
        get => $this->online;
    }

    #[ORM\Column(name: 'last_login', type: 'integer', options: ['default' => 0])]
    #[Groups(['user:read'])]
    public private(set) int $lastLogin = 0 {
        get => $this->lastLogin;
    }

    #[ORM\Column(name: 'last_online', type: 'integer', nullable: true)]
    #[Groups(['user:read'])]
    public private(set) ?int $lastOnline = null {
        get => $this->lastOnline;
    }

    #[ORM\Column(name: 'account_created', type: 'integer', nullable: true)]
    #[Groups(['user:read'])]
    public private(set) ?int $accountCreated = null {
        get => $this->accountCreated;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function updateUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function updateMail(string $mail): static
    {
        $this->mail = $mail;
        return $this;
    }

    public function updateLook(?string $look): static
    {
        $this->look = $look;
        return $this;
    }

    public function updateGender(string $gender): static
    {
        $this->gender = $gender;
        return $this;
    }

    public function updateMotto(?string $motto): static
    {
        $this->motto = $motto;
        return $this;
    }

    public function setCredits(int $credits): static
    {
        $this->credits = $credits;
        return $this;
    }

    public function setRank(int $rank): static
    {
        $this->rank = $rank;
        return $this;
    }

    public function setAuthTicket(?string $authTicket): static
    {
        $this->authTicket = $authTicket;
        return $this;
    }

    public function getAuthTicket(): ?string
    {
        return $this->authTicket;
    }

    public function setIpRegister(string $ipRegister): static
    {
        $this->ipRegister = $ipRegister;
        return $this;
    }

    public function setIpCurrent(?string $ipCurrent): static
    {
        $this->ipCurrent = $ipCurrent;
        return $this;
    }

    public function setOnline(int $online): static
    {
        $this->online = $online;
        return $this;
    }

    public function setLastLogin(int $lastLogin): static
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function setLastOnline(?int $lastOnline): static
    {
        $this->lastOnline = $lastOnline;
        return $this;
    }

    public function setAccountCreated(?int $accountCreated): static
    {
        $this->accountCreated = $accountCreated;
        return $this;
    }
}
