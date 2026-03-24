You are a senior PHP backend engineer. Initialize a production-grade Symfony project from scratch with a clean architecture and best practices.

## Goal

Build a high-load ready REST API service using Symfony (latest stable) with API Platform, following DDD (Domain-Driven Design).

## Tech Stack

* PHP 8.3+
* Symfony (use skeleton)
* API Platform (REST only, no GraphQL)
* MySQL (with persistent storage)
* Redis (for counters and caching)
* RabbitMQ (message broker)
* Mailhog (for email testing)
* Docker + Docker Compose
* Doctrine ORM + Migrations + Fixtures

## Architecture

Use DDD structure:

src/

* Domain/

    * Entity/
    * ValueObject/
    * Repository/
* Application/

    * DTO/
    * Service/
    * UseCase/
* Infrastructure/

    * Persistence/
    * Messaging/
    * Mail/
* UI/

    * Controller/ (API Platform resources)

Apply:

* DTO for input/output
* ValueObjects for domain integrity
* Services for business logic
* Repositories (interfaces in Domain, implementations in Infrastructure)
* Validation (Symfony Validator)
* Attributes instead of YAML/XML configs wherever possible

## Features

### 1. User Management

Create User entity with:

* id (UUID)
* email (unique)
* password (hashed, modern algorithm: bcrypt or argon2id)
* isVerified (bool)
* lastLoginAt (datetime nullable)
* createdAt

### 2. Authentication (Passwordless via Email)

* User enters email
* System generates a login token
* Token is sent via email (Mailhog)
* Email sending must go through RabbitMQ (async)
* Token validation endpoint logs user in
* Update lastLoginAt
* Store token securely (hashed or short-lived)

### 3. Redis გამოყენება

* Store login attempt counters per user/email
* Implement basic rate limiting (e.g. max N attempts per minute)

### 4. API Platform

Expose endpoints:

* POST /auth/request-login-link
* POST /auth/confirm-token
* GET /users (admin only or protected)
* GET /me

Use DTOs for input/output, not entities directly.

### 5. Database

* MySQL with Docker volume (data must persist)
* Create migration for User table
* Add indexes where appropriate
* Use Doctrine Migrations

### 6. Fixtures

* Create test users
* Include at least 5 sample users

### 7. Messaging (RabbitMQ microservice)

* Separate service for message consumption (email sender)
* Producer in main app
* Consumer as separate container/service
* Use Symfony Messenger

### 8. Docker Setup

docker-compose should include:

* php-fpm
* nginx
* mysql (with volume)
* redis
* rabbitmq (with management UI)
* mailhog
* worker (consumer)

All services must be networked properly.

### 9. Security

* Use Symfony Security
* Stateless authentication (token-based)
* Proper password hashing (argon2id preferred)
* Validation on all inputs

### 10. Code Quality

* Use strict types
* PSR-12
* Clear separation of concerns
* No anemic domain model
* Use constructor property promotion
* Use readonly where applicable

## Output Requirements

* Fully working project structure
* All configs included (Docker, Symfony, services)
* Example .env
* Commands to run project
* Migration + fixtures ready to run
* Clear README with setup instructions

## Important

* Do NOT simplify architecture
* Do NOT skip DDD layers
* Do NOT use legacy Symfony practices
* Prefer attributes over YAML/XML configs
* Ensure everything runs via Docker only

Generate complete project files and structure.
