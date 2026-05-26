# Symfony DDD High-Load REST API

Production-grade stateless REST API on Symfony + API Platform with DDD architecture, MySQL, Redis, RabbitMQ, Mailhog, Docker Compose, Doctrine Migrations, and Fixtures.

## Stack

- PHP 8.3+
- Symfony 7.3
- API Platform 3 (REST)
- MySQL 8.4
- Redis 7
- RabbitMQ 3 (management UI)
- Mailhog
- Doctrine ORM + Migrations + Fixtures
- Symfony Messenger
- web3php (`web3p/web3.php`)

## Project Structure

`src/` is split by architectural layers:

- `Domain/`
- `Entity/` business entities (`User`, `LoginToken`, `AccessToken`)
- `ValueObject/` strict domain value objects (`Email`)
- `Repository/` repository interfaces
- `Application/`
- `DTO/` API input/output models
- `Service/` reusable application services (`TokenManager`, mappers, rate limiter)
- `UseCase/` business scenarios (`RequestLoginLinkUseCase`, `ConfirmLoginTokenUseCase`, `SignInWithPasswordUseCase`)
- `Exception/` application-level exceptions
- `Infrastructure/`
- `Persistence/Doctrine/Repository/` Doctrine implementations of domain repositories
- `Messaging/` async messages and handlers
- `Mail/` mail transport adapter
- `Security/` bearer token authenticator
- `Web3/` adapter over `web3php` RPC client
- `UI/`
- `Controller/` API Platform resources (route contracts)
- `Processor/` write-side handlers
- `Provider/` read-side handlers

## Endpoints

All API Platform routes are prefixed by `/api`.

Public:

- `POST /api/auth/register`
- `POST /api/auth/login-links`
- `POST /api/auth/confirm-token`

Authenticated:

- `GET /api/me`
- `POST /api/web3/wallets`
- `GET /api/web3/wallets`
- `GET /api/web3/wallets/{id}`
- `PATCH /api/web3/wallets/{id}`
- `DELETE /api/web3/wallets/{id}`
- `GET /api/web3/wallets/{id}/balance`

Admin (`ROLE_ADMIN`):

- `GET /api/admin/users`
- `POST /api/admin/users`
- `GET /api/admin/users/{id}`
- `PATCH /api/admin/users/{id}`
- `DELETE /api/admin/users/{id}`

## How The Application Works

### 1) Passwordless login request

1. Client sends email to `POST /api/auth/login-links`.
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
6. Registration context is persisted in a dedicated `user_registration_context` table with fingerprint/IP hashes.
7. System generates login token and sends registration email asynchronously through RabbitMQ.

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

### 3.1) Browser password sign-in

1. Browser users submit `Email` + `Password` from `/app/auth`.
2. `SignInWithPasswordUseCase` normalizes email, applies the Redis-backed login rate limit, checks that the email exists in `users`, and verifies the password hash.
3. Failed email/password checks return the same generic error to avoid public user enumeration; structured logs keep only hashed email context.
4. Successful sign-in updates `lastLoginAt`, issues a hashed access token, and keeps the raw token only in the Symfony web session.

### 4) Authenticated requests

1. Client calls protected endpoint with `Authorization: Bearer <token>`.
2. `BearerTokenAuthenticator` hashes incoming token and verifies it in `access_tokens`.
3. If valid and not expired, Symfony security context is populated with `User`.
4. Providers (`MeProvider`, admin providers) return DTO responses.

### 5) Admin user management via API Platform

1. Admin calls `/api/admin/users*` endpoints.
2. Access is enforced by `access_control`.
3. `PATCH /api/admin/users/{id}` is partial update only and changes only explicitly provided fields. At the moment this contract is limited to `roles`.
4. `AdminCreateUserProcessor` / `AdminUpdateUserProcessor` / `AdminDeleteUserProcessor` perform write operations through domain repositories.
5. Admin outputs include hashed registration context from the dedicated projection table.

### 6) Web3 wallet tracking

1. Authenticated user sends `POST /api/web3/wallets` with wallet address and optional `rpcPreset` (`ethereum`, `arbitrum`, `optimism`, `base`, `polygon`, `bsc`, `avalanche`) and optional custom `rpcEndpoint`.
2. Service validates EVM address and auto-selects public RPC endpoint from preset when custom endpoint is not provided.
3. Service resolves/stores `networkId` and wallet metadata in `web3_wallets` (custom RPC is validated against selected preset if both are provided).
4. Client calls `GET /api/web3/wallets/{id}/balance`.
5. Service fetches on-chain balance via `web3php` (`eth_getBalance`), persists `lastKnownBalanceWei` + `lastSyncedAt`, and returns normalized + human-readable balance fields.

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

## Static Analysis

PHPStan is configured with Symfony and Doctrine extensions, elevated strictness (`level 8`), and a committed baseline for legacy issues.

Convenient commands:

```bash
./bin/phpstan
docker compose exec php composer stan
docker compose exec php composer phpstan:baseline
```

Before the first run, install dev dependencies:

```bash
docker compose exec php composer install
docker compose exec php php bin/console cache:warmup
```

CI runs static analysis in [`.github/workflows/phpstan.yml`](/home/ilya1420/symfony/.github/workflows/phpstan.yml) through Docker Compose to match the local environment.
Workflow uses Composer cache (`vendor` + `~/.composer/cache/files`) and executes install, cache warmup, and PHPStan in a single container run to reduce total duration.

## Service URLs

- API root: `http://localhost:8080/api`
- Web UI: `http://localhost:8080/app/auth`
- App URL used in emails: `http://localhost:8080`
- RabbitMQ UI: `http://localhost:15672` (`guest` / `guest`)
- Mailhog UI: `http://localhost:8025`

## Admin Fixture Credentials

- Email: `admin@example.com`
- Password: `AdminPass123!`
- Role: `ROLE_ADMIN`

## Operational Notes

- API endpoints are stateless. The demo web UI uses a dedicated Symfony session firewall backed by hashed access tokens.
- MySQL data persists in `mysql_data` volume.
- Redis persists in `redis_data` volume.
- RabbitMQ data persists in `rabbitmq_data` volume.
- Token security model: store only SHA-256 token hashes in DB.
- Anti-abuse registration data is stored separately from `users` in `user_registration_context`.
- DTOs are used for all API contracts; Doctrine entities are never exposed directly.
- SMTP sender is configured via `MAILER_FROM`.
- Web3 defaults are configured via `WEB3_DEFAULT_RPC_URL` and `WEB3_REQUEST_TIMEOUT`.
- Success responses are unified JSON: `{"data": ...}`.
- Error responses are unified JSON: `{"message":"<text>"}` with proper HTTP status code.
- API errors are logged by Monolog and visible in container logs.
- Messenger transport serializer uses JSON contract without PHP envelope/stamps/class names.

## Manual API Examples

Create login link:

```bash
curl -X POST http://localhost:8080/api/auth/login-links \
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

Admin update user roles:

```bash
curl -X PATCH http://localhost:8080/api/admin/users/<user-id> \
  -H 'Content-Type: application/merge-patch+json' \
  -H 'Authorization: Bearer <admin-access-token>' \
  -d '{"roles":["ROLE_ADMIN"]}'
```

Admin delete user:

```bash
curl -X DELETE http://localhost:8080/api/admin/users/<user-id> \
  -H 'Authorization: Bearer <admin-access-token>'
```

Create tracked web3 wallet (auto RPC by preset):

```bash
curl -X POST http://localhost:8080/api/web3/wallets \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <access-token>' \
  -d '{"address":"0x742d35Cc6634C0532925a3b844Bc454e4438f44e","rpcPreset":"base"}'
```

Refresh and read wallet balance:

```bash
curl http://localhost:8080/api/web3/wallets/<wallet-id>/balance \
  -H 'Authorization: Bearer <access-token>'
```

Delete wallet:

```bash
curl -X DELETE http://localhost:8080/api/web3/wallets/<wallet-id> \
  -H 'Authorization: Bearer <access-token>'
```

## Web UI Layout

The demo UI is structurally separated from API Platform under `src/UI/Web`, `templates/web_app`, and `public/web-app`.
It uses Twig, Symfony Form, DTO validation attributes, CSRF protection, and a dedicated session authenticator for browser access.

- `/app/auth` - sign in with email/password
- `/app/auth/register` - create account; browser device fingerprint is collected automatically and submitted as a hidden field
- `/app/auth/confirm` - confirm token using the email stored in the web session
- `/app` - wallet overview dashboard
- `/app/wallets/new` - add wallet with EVM RPC preset dropdown
- `/app/wallets/{id}` - wallet detail, balance widget, sync/update/delete actions
