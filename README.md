# Symfony DDD High-Load REST API

Production-grade stateless REST API on Symfony + API Platform with DDD architecture, MySQL, Redis, RabbitMQ, Mailhog, Docker Compose, Doctrine Migrations, and Fixtures.

## Stack

- PHP 8.3+
- Symfony 7.3
- API Platform 3 (REST)
- MySQL 8
- Redis 7
- RabbitMQ 3 (management UI)
- Mailhog
- Doctrine ORM + Migrations + Fixtures
- Symfony Messenger

## Project Structure

`src/` is split by architectural layers:

- `Domain/`
- `Entity/` business entities (`User`, `LoginToken`, `AccessToken`)
- `ValueObject/` strict domain value objects (`Email`)
- `Repository/` repository interfaces
- `Application/`
- `DTO/` API input/output models
- `Service/` reusable application services (`TokenManager`, mappers, rate limiter)
- `UseCase/` business scenarios (`RequestLoginLinkUseCase`, `ConfirmLoginTokenUseCase`)
- `Exception/` application-level exceptions
- `Infrastructure/`
- `Persistence/Doctrine/Repository/` Doctrine implementations of domain repositories
- `Messaging/` async messages and handlers
- `Mail/` mail transport adapter
- `Security/` bearer token authenticator
- `UI/`
- `Controller/` API Platform resources (route contracts)
- `Processor/` write-side handlers
- `Provider/` read-side handlers

## Endpoints

All API Platform routes are prefixed by `/api`.

Public:

- `POST /api/auth/register`
- `POST /api/auth/request-login-link`
- `POST /api/auth/confirm-token`

Authenticated:

- `GET /api/me`

Admin (`ROLE_ADMIN`):

- `GET /api/admin/users`
- `POST /api/admin/users`
- `GET /api/admin/users/{id}`
- `PATCH /api/admin/users/{id}`
- `DELETE /api/admin/users/{id}`

## How The Application Works

### 1) Passwordless login request

1. Client sends email to `POST /api/auth/request-login-link`.
2. `RequestLoginLinkProcessor` invokes `RequestLoginLinkUseCase`.
3. `LoginRateLimiterService` uses Redis-backed limiter and enforces N requests/minute per email.
4. If user exists:
- `TokenManager` generates raw token.
- Hash of token is stored in `login_tokens` (raw token is never stored in DB).
- `SendLoginLinkEmailMessage` is dispatched to Messenger transport `async`.
5. API returns status response immediately; email is sent asynchronously by worker.

### 1.1) Registration with anti-multiaccounting

1. Client sends `POST /api/auth/register` with `email`, `password`, `deviceFingerprint`.
2. API validates password policy and fingerprint format.
3. `MultiAccountGuardService` checks one account per device fingerprint (Redis key by hashed fingerprint).
4. `MultiAccountGuardService` checks max registrations per IP per day (`REGISTRATION_MAX_PER_IP_DAY`).
5. If checks pass, user is created with `ROLE_USER`.
6. System generates login token and sends registration email asynchronously through RabbitMQ.

### 2) Asynchronous email delivery

1. RabbitMQ receives `SendLoginLinkEmailMessage`.
2. `worker` container runs `messenger:consume async` continuously.
3. `SendLoginLinkEmailMessageHandler` calls `LoginLinkMailer`.
4. Email is delivered via SMTP to Mailhog.
5. Mail body includes one-time token and the target confirm endpoint.

RabbitMQ message body format (cross-language friendly JSON):

```json
{
  "type": "send_login_link_email",
  "payload": {
    "email": "alice@example.com",
    "token": "raw-token"
  }
}
```

### 3) Token confirmation and bearer issuance

1. Client submits `POST /api/auth/confirm-token` with `{email, token}`.
2. `ConfirmLoginTokenUseCase`:
- hashes raw token,
- loads matching valid non-used login token,
- marks login token as used,
- updates user (`lastLoginAt`, `isVerified`),
- generates access token,
- stores only hash of access token in `access_tokens`.
3. API returns bearer token and expiry timestamp.

### 4) Authenticated requests

1. Client calls protected endpoint with `Authorization: Bearer <token>`.
2. `BearerTokenAuthenticator` hashes incoming token and verifies it in `access_tokens`.
3. If valid and not expired, Symfony security context is populated with `User`.
4. Providers (`MeProvider`, admin providers) return DTO responses.

### 5) Admin user management via API Platform

1. Admin calls `/api/admin/users*` endpoints.
2. Access is enforced by both operation security expressions and `access_control`.
3. `AdminCreateUserProcessor` / `AdminUpdateUserProcessor` / `AdminDeleteUserProcessor` perform write operations through domain repositories.
4. `AdminUsersProvider` and `AdminUserItemProvider` return `UserOutput` DTO only.

## Run With Docker

1. Start containers:

```bash
docker compose up -d --build
```

2. Install dependencies:

```bash
docker compose exec php composer install
```

3. Run migrations:

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

4. Load fixtures:

```bash
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

5. Verify worker:

```bash
docker compose logs -f worker
```

## Service URLs

- API root: `http://localhost:8080/api`
- RabbitMQ UI: `http://localhost:15672` (`guest` / `guest`)
- Mailhog UI: `http://localhost:8025`

## Admin Fixture Credentials

- Email: `admin@example.com`
- Password: `AdminPass123!`
- Role: `ROLE_ADMIN`

## Operational Notes

- API is stateless; sessions are disabled.
- MySQL data persists in `mysql_data` volume.
- Redis persists in `redis_data` volume.
- RabbitMQ data persists in `rabbitmq_data` volume.
- Token security model: store only SHA-256 token hashes in DB.
- DTOs are used for all API contracts; Doctrine entities are never exposed directly.
- SMTP sender is configured via `MAILER_FROM`.
- Success responses are unified JSON: `{"data": ...}`.
- Error responses are unified JSON: `{"message":"<text>"}` with proper HTTP status code.
- API errors are logged by Monolog and visible in container logs.
- Messenger transport serializer uses JSON contract without PHP envelope/stamps/class names.

## Manual API Examples

Request login link:

```bash
curl -X POST http://localhost:8080/api/auth/request-login-link \
  -H 'Content-Type: application/json' \
  -d '{"email":"alice@example.com"}'
```

Register:

```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"new-user@example.com","password":"StrongPass123!","deviceFingerprint":"device-4f95bca6d8f64a93"}'
```

Confirm token:

```bash
curl -X POST http://localhost:8080/api/auth/confirm-token \
  -H 'Content-Type: application/json' \
  -d '{"email":"alice@example.com","token":"<token-from-email>"}'
```

Read profile:

```bash
curl http://localhost:8080/api/me \
  -H 'Authorization: Bearer <access-token>'
```

Admin list users:

```bash
curl http://localhost:8080/api/admin/users \
  -H 'Authorization: Bearer <admin-access-token>'
```

Admin create user:

```bash
curl -X POST http://localhost:8080/api/admin/users \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <admin-access-token>' \
  -d '{"email":"new-user@example.com","password":"StrongPass123!","roles":["ROLE_USER"],"isVerified":false}'
```
