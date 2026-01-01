<?php

declare(strict_types=1);

namespace App\Application\User\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterUserDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Username is required')]
        #[Assert\Type('string')]
        #[Assert\Length(min: 2, max: 25)]
        #[Assert\Regex(
            pattern: '/^[a-zA-Z\-=!?@:,.\d]+$/',
            message: 'Username can only contain letters, numbers and -=!?@:,.'
        )]
        public mixed $username = null,

        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Type('string')]
        #[Assert\Email]
        #[Assert\Length(min: 9)]
        public mixed $mail = null,

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Type('string')]
        #[Assert\Length(min: 6)]
        public mixed $password = null,

        #[Assert\NotBlank(message: 'Password confirmation is required')]
        #[Assert\Type('string')]
        #[Assert\EqualTo(propertyPath: 'password', message: 'Passwords must match')]
        #[SerializedName('password_confirmation')]
        public mixed $passwordConfirmation = null,

        #[Assert\NotBlank(message: 'Look is required')]
        #[Assert\Type('string')]
        public mixed $look = null,

        #[Assert\NotBlank(message: 'Gender is required')]
        #[Assert\Type('string')]
        #[Assert\Choice(choices: ['M', 'F'], message: 'Gender must be M or F')]
        public mixed $gender = 'M',
    ) {}
}
