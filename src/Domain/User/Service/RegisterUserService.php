<?php

declare(strict_types=1);

namespace App\Domain\User\Service;

use App\Application\User\DTO\RegisterUserDTO;
use App\Domain\Shared\Exception\ValidationException;
use App\Domain\User\Entity\User;
use App\Domain\User\Entity\UserCurrency;
use App\Domain\User\Entity\UserSetting;
use App\Domain\User\Enum\CurrencyType;
use App\Domain\User\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegisterUserService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private int $startCredits = 0,
        private int $startPoints = 0,
        private int $startPixels = 0,
        private string $startMotto = '',
        private int $maxAccountsPerIp = 3,
    ) {}

    public function register(RegisterUserDTO $dto, string $ipAddress): User
    {
        $this->validateUniqueUser($dto->username, $dto->mail);
        $this->validateIpLimit($ipAddress);

        $user = $this->createUser($dto, $ipAddress);
        $this->initializeUserSettings($user);
        $this->initializeUserCurrencies($user);

        $this->userRepository->save($user);

        return $user;
    }

    private function validateUniqueUser(string $username, string $mail): void
    {
        if ($this->userRepository->findByUsername($username)) {
            throw new ValidationException(
                ['username' => 'Username is already taken'],
                'Username is already taken'
            );
        }

        if ($this->userRepository->findByEmail($mail)) {
            throw new ValidationException(
                ['mail' => 'Email is already registered'],
                'Email is already registered'
            );
        }
    }

    private function validateIpLimit(string $ipAddress): void
    {
        $count = $this->userRepository->countByIp($ipAddress);

        if ($count >= $this->maxAccountsPerIp) {
            throw new ValidationException(
                ['ip' => "Maximum {$this->maxAccountsPerIp} accounts per IP allowed"],
                'Account limit reached for this IP'
            );
        }
    }

    private function createUser(RegisterUserDTO $dto, string $ipAddress): User
    {
        $user = new User();

        $user->updateUsername($dto->username);
        $user->updateMail($dto->mail);
        $user->updateLook($dto->look);
        $user->updateGender($dto->gender);
        $user->updateMotto($this->startMotto);
        $user->setCredits($this->startCredits);
        $user->setRank(1);
        $user->setIpRegister($ipAddress);
        $user->setIpCurrent($ipAddress);
        $user->setLastLogin(time());
        $user->setLastOnline(time());
        $user->setAccountCreated(time());

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        return $user;
    }

    private function initializeUserSettings(User $user): void
    {
        $settings = new UserSetting();
        $user->setSettings($settings);
    }

    private function initializeUserCurrencies(User $user): void
    {
        $points = new UserCurrency();
        $points->setType(CurrencyType::POINTS->value);
        $points->setAmount($this->startPoints);
        $user->addCurrency($points);

        $pixels = new UserCurrency();
        $pixels->setType(CurrencyType::PIXELS->value);
        $pixels->setAmount($this->startPixels);
        $user->addCurrency($pixels);
    }
}
