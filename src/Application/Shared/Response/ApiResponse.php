<?php

declare(strict_types=1);

namespace App\Application\Shared\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApiResponse
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $message = null,
        public int $code = Response::HTTP_OK,
        public array $errors = [],
    ) {}

    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $code = Response::HTTP_OK,
    ): self {
        return new self(
            success: true,
            data: $data,
            message: $message,
            code: $code,
        );
    }

    public static function created(mixed $data = null, ?string $message = null): self
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    public static function error(
        ?string $message = null,
        int $code = Response::HTTP_BAD_REQUEST,
        array $errors = [],
    ): self {
        return new self(
            success: false,
            message: $message,
            code: $code,
            errors: $errors,
        );
    }

    public static function notFound(?string $message = 'Resource not found'): self
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }

    public static function unauthorized(?string $message = 'Unauthorized'): self
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(?string $message = 'Forbidden'): self
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }

    public static function validationError(array $errors, ?string $message = 'Validation failed'): self
    {
        return new self(
            success: false,
            message: $message,
            code: Response::HTTP_UNPROCESSABLE_ENTITY,
            errors: $errors,
        );
    }

    public function toJsonResponse(): JsonResponse
    {
        $payload = [
            'success' => $this->success,
            'code' => $this->code,
        ];

        if ($this->message !== null) {
            $payload['message'] = $this->message;
        }

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        }

        if (!empty($this->errors)) {
            $payload['errors'] = $this->errors;
        }

        return new JsonResponse($payload, $this->code);
    }
}
