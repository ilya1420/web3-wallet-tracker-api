# Symfony DDD High-Load REST API

Production-grade Symfony API skeleton with DDD, API Platform, MySQL, Redis, RabbitMQ, Mailhog, Docker Compose, Doctrine Migrations, and Fixtures.

## Stack

- PHP 8.3+
- Symfony 7.3 (skeleton-style structure)
- API Platform (REST)
- MySQL 8.4
- Redis 7
- RabbitMQ 3 (management UI)
- Mailhog
- Doctrine ORM + Migrations + Fixtures
- Symfony Messenger (async email delivery)

## Architecture

`src/`

- `Domain/`
- `Entity/`
- `ValueObject/`
- `Repository/` (interfaces)
- `Application/`
- `DTO/`
- `Service/`
- `UseCase/`
- `Infrastructure/`
- `Persistence/Doctrine/Repository/` (implementations)
- `Messaging/`
- `Mail/`
- `Security/`
- `UI/`
- `Controller/` (API Platform resources)
- `Processor/`
- `Provider/`

## Exposed Endpoints

- `POST /api/auth/request-login-link`
- `POST /api/auth/confirm-token`
- `GET /api/me` (authenticated)
- `GET /api/users` (admin only)
- `GET /api/admin/users` (admin list)
- `POST /api/admin/users` (admin create)
- `GET /api/admin/users/{id}` (admin details)
- `PATCH /api/admin/users/{id}` (admin update)
- `DELETE /api/admin/users/{id}` (admin delete)

## Auth Flow

1. Client sends email to `POST /api/auth/request-login-link`.
2. System rate-limits by email (Redis-backed limiter).
3. Short-lived login token is generated, hashed, stored in DB.
4. Raw token is sent asynchronously through RabbitMQ.
5. Worker consumes message and sends email via Mailhog SMTP.
6. Client confirms via `POST /api/auth/confirm-token`.
7. API returns stateless bearer access token.
8. `GET /api/me` and protected endpoints use bearer token authentication.

## Run With Docker

1. Start infrastructure and app containers:

```bash
docker compose up -d --build
```

2. Install dependencies in the PHP container:

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

5. Verify worker is running:

```bash
docker compose logs -f worker
```

## Service URLs

- API: `http://localhost:8080`
- API docs: `http://localhost:8080/api`
- RabbitMQ UI: `http://localhost:15672` (`guest` / `guest`)
- Mailhog UI: `http://localhost:8025`

## Admin Access

- Fixture admin credentials: `admin@example.com` / `AdminPass123!`
- Required role: `ROLE_ADMIN`
- Authentication method: bearer token from `POST /api/auth/confirm-token`

## Example Requests

Request login link:

```bash
curl -X POST http://localhost:8080/api/auth/request-login-link \
  -H 'Content-Type: application/json' \
  -d '{"email":"alice@example.com"}'
```

Confirm token:

```bash
curl -X POST http://localhost:8080/api/auth/confirm-token \
  -H 'Content-Type: application/json' \
  -d '{"email":"alice@example.com","token":"<token-from-email>"}'
```

Get profile:

```bash
curl http://localhost:8080/api/me \
  -H 'Authorization: Bearer <access-token>'
```

Get users (admin token required):

```bash
curl http://localhost:8080/api/admin/users \
  -H 'Authorization: Bearer <admin-access-token>'
```

Create user (admin token required):

```bash
curl -X POST http://localhost:8080/api/admin/users \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <admin-access-token>' \
  -d '{"email":"new-user@example.com","password":"StrongPass123!","roles":["ROLE_USER"],"isVerified":false}'
```

## Notes

- Everything is designed to run from Docker only.
- `mysql_data`, `redis_data`, and `rabbitmq_data` volumes persist state.
- Password hasher is `argon2id`.
- API resources use DTO input/output, not Doctrine entities.
