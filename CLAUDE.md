# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **veloce-symfony-api**, a PHP 8.4 REST API for a Habbo CMS built on Symfony 8.0. It provides user authentication, registration, and profile management with JWT-based authentication.

## Development Commands

```bash
composer install                           # Install dependencies
php bin/console cache:clear                # Clear application cache
php bin/console doctrine:migrations:migrate # Run database migrations
php bin/console doctrine:migrations:diff   # Generate migration from entity changes
php bin/console debug:router               # List all registered routes
vendor/bin/phpstan analyse                 # Run static analysis (level 5)
symfony server:start                       # Start local dev server (requires Symfony CLI)
```

## Architecture

The codebase follows Domain-Driven Design with clear layer separation:

```
src/
├── Application/       # Use cases, DTOs, response objects
│   ├── Shared/Response/ApiResponse.php    # Standard API response wrapper
│   └── {Module}/DTO/                      # Request DTOs with validation
├── Domain/            # Business logic, entities, repositories
│   ├── Shared/        # Base classes and exceptions
│   └── {Module}/
│       ├── Entity/    # Doctrine entities with PHP 8.4 property hooks
│       ├── Repository/
│       └── Service/   # Domain services
├── Infrastructure/    # Framework integrations, listeners
│   ├── EventListener/ExceptionListener.php # Global exception → ApiResponse
│   └── Locale/LocaleSubscriber.php         # Locale extraction from URL
└── Presentation/      # HTTP layer
    └── Controller/Api/
        ├── AbstractApiController.php       # Base with success/error helpers
        └── {Module}/                       # Route controllers
```

### Key Patterns

**API Responses**: All controllers extend `AbstractApiController` and return standardized JSON:
```php
return $this->success($data);           // { success: true, code: 200, data: ... }
return $this->created($data);           // { success: true, code: 201, data: ... }
return $this->error('message', 400);    // { success: false, code: 400, message: ... }
return $this->validationError($errors); // { success: false, code: 422, errors: ... }
```

**Request Validation**: Use `#[MapRequestPayload]` with DTO classes containing Symfony Validator constraints:
```php
public function register(#[MapRequestPayload] RegisterUserDTO $dto): JsonResponse
```

**Routing**: All API routes are locale-prefixed (`/{_locale}/...`) with supported locales: en, fr, de, es, pt, nl. Routes use PHP 8 attributes:
```php
#[Route('/user', name: 'api_user_')]
class UserController extends AbstractApiController
{
    #[Route('/ticket', name: 'ticket', methods: ['PUT'])]
    public function generateTicket(#[CurrentUser] User $user): JsonResponse
```

**Entities**: Use PHP 8.4 property hooks with Doctrine ORM attributes and Serializer groups:
```php
#[ORM\Column(length: 25, unique: true)]
#[Groups(['user:read', 'user:list'])]
public private(set) string $username {
    get => $this->username;
    set => strtolower(trim($value));
}
```

**Domain Exceptions**: Throw `DomainException` subclasses (e.g., `ValidationException`, `NotFoundException`) - they're automatically converted to `ApiResponse` by `ExceptionListener`.

### Authentication

- JWT authentication via `lexik/jwt-authentication-bundle`
- Login endpoint: `POST /{locale}/auth/login` with `json_login`
- Access current user: `#[CurrentUser] User $user` parameter attribute
- Public routes configured in `config/packages/security.yaml` access_control

## Configuration

**Hotel Parameters** (in `config/services.yaml`):
- `hotel.start_credits`, `hotel.start_points`, `hotel.start_pixels`
- `hotel.start_motto`, `hotel.max_accounts_per_ip`

**Environment Variables** (`.env`):
- `DATABASE_URL`: MySQL connection string
- `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`, `JWT_TTL`: JWT configuration
- `CORS_ALLOW_ORIGIN`: Allowed CORS origins regex
- `APP_ENV`: dev/prod/test

## Database

- ORM: Doctrine with attribute mapping
- Entities live in `src/Domain/*/Entity/`
- Migrations in `migrations/` (namespace `DoctrineMigrations`)
