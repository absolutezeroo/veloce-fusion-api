<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Shared\Response\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractApiController extends AbstractController
{
    protected function respond(ApiResponse $response): JsonResponse
    {
        return $response->toJsonResponse();
    }

    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $code = Response::HTTP_OK,
    ): JsonResponse {
        return ApiResponse::success($data, $message, $code)->toJsonResponse();
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return ApiResponse::created($data, $message)->toJsonResponse();
    }

    protected function error(
        ?string $message = null,
        int $code = Response::HTTP_BAD_REQUEST,
        array $errors = [],
    ): JsonResponse {
        return ApiResponse::error($message, $code, $errors)->toJsonResponse();
    }

    protected function notFound(?string $message = 'Resource not found'): JsonResponse
    {
        return ApiResponse::notFound($message)->toJsonResponse();
    }

    protected function validationError(array $errors, ?string $message = 'Validation failed'): JsonResponse
    {
        return ApiResponse::validationError($errors, $message)->toJsonResponse();
    }
}
