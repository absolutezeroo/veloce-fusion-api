<?php

declare(strict_types=1);

namespace App\Application\User\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterUserDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 12)]
        #[Assert\Regex(
            pattern: '/^[a-zA-Z\-=!?@:,.\d]+$/',
            message: 'Username can only contain letters, numbers and -=!?@:,.'
        )]
        public string $username,

        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(min: 9)]
        public string $mail,

        #[Assert\NotBlank]
        #[Assert\Length(min: 6)]
        public string $password,

        #[Assert\NotBlank]
        #[Assert\EqualTo(propertyPath: 'password', message: 'Passwords must match')]
        public string $passwordConfirmation,

        #[Assert\NotBlank]
        public string $look,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['M', 'F'])]
        public string $gender = 'M',
    ) {}
}
