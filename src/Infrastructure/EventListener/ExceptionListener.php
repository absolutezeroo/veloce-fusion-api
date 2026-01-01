<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Application\Shared\Response\ApiResponse;
use App\Domain\Shared\Exception\DomainException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ExceptionListener
{
    public function __construct(
        private string $environment,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $response = match (true) {
            $exception instanceof DomainException => ApiResponse::error(
                message: $exception->getMessage(),
                code: $exception->statusCode,
                errors: $exception->errors,
            ),
            // Convert Access Denied to 401 Unauthorized for API
            $exception instanceof AccessDeniedException,
            $exception instanceof AccessDeniedHttpException => ApiResponse::error(
                message: 'Authentication required',
                code: Response::HTTP_UNAUTHORIZED,
            ),
            $exception instanceof HttpExceptionInterface => ApiResponse::error(
                message: $exception->getMessage(),
                code: $exception->getStatusCode(),
            ),
            // Handle "no logged-in user" error as 401
            str_contains($exception->getMessage(), 'no logged-in user') => ApiResponse::error(
                message: 'Authentication required',
                code: Response::HTTP_UNAUTHORIZED,
            ),
            default => $this->handleGenericException($exception),
        };

        $event->setResponse($response->toJsonResponse());
    }

    private function handleGenericException(\Throwable $exception): ApiResponse
    {
        $message = $this->environment === 'dev'
            ? $exception->getMessage()
            : 'An internal server error occurred';

        return ApiResponse::error(
            message: $message,
            code: Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
