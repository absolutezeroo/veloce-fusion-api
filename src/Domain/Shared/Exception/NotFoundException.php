<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

use Symfony\Component\HttpFoundation\Response;

final class NotFoundException extends DomainException
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct($message, Response::HTTP_NOT_FOUND);
    }
}
