<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

use Exception;

class DomainException extends Exception
{
    public function __construct(
        string $message = '',
        public readonly int $statusCode = 400,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $statusCode);
    }
}
