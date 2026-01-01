<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

use Symfony\Component\HttpFoundation\Response;

final class ValidationException extends DomainException
{
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }
}
