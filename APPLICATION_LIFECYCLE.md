# Жизненный цикл приложения

## 1. Назначение системы

Это stateless REST API на Symfony 7.3 и API Platform 3. Приложение строится вокруг passwordless-аутентификации по email, выдачи bearer-токенов, админского управления пользователями и anti-abuse логики на Redis.

Система разделена на четыре слоя:

- `Domain` хранит бизнес-сущности, value object и интерфейсы репозиториев.
- `Application` описывает use case, DTO и прикладные сервисы.
- `Infrastructure` реализует хранение, доставку сообщений, mail и security integration.
- `UI` задает HTTP-контракты через API Platform resources, processors и providers.

Ключевая идея архитектуры: HTTP, Doctrine, Redis, RabbitMQ и Mailer не проникают в доменную модель напрямую. Они подключаются на границе инфраструктуры и прикладного слоя.

## 2. Инфраструктурный bootstrap

Приложение запускается только через Docker Compose. Основные контейнеры:

- `php` исполняет Symfony приложение и CLI-команды.
- `nginx` принимает HTTP-трафик и проксирует PHP-запросы в `php-fpm`.
- `postgres` хранит пользователей, login tokens, access tokens, registration context, outbox и web3 wallet state.
- `redis` используется как backend для `cache.app` и rate limiter.
- `rabbitmq` принимает async-сообщения на отправку email.
- `mailhog` принимает SMTP-письма для локальной проверки.
- `worker` отдельно потребляет очередь `async` через `messenger:consume`.

На старте контейнеров никакой инициализации БД автоматически не происходит. Рабочее состояние приложения достигается после:

1. `composer install`
2. `doctrine:migrations:migrate`
3. `doctrine:fixtures:load`

Это решение упрощает контроль над окружением, но увеличивает риск неполной инициализации среды при первом запуске.

## 3. Конфигурационная модель

Поведение приложения задается через `.env`:

- `DATABASE_URL` подключает PostgreSQL.
- `REDIS_URL` подключает Redis как единый backend для cache и limiter.
- `MESSENGER_TRANSPORT_DSN` указывает AMQP transport.
- `MAILER_DSN` направляет письма в Mailhog.
- `ACCESS_TOKEN_TTL` задает срок жизни access token.
- `LOGIN_TOKEN_TTL` задает срок жизни login link token.
- `LOGIN_RATE_LIMIT` ограничивает число login-link запросов в минуту.
- `REGISTRATION_MAX_PER_IP_DAY` ограничивает число регистраций с IP в сутки.

Это делает сервис параметризуемым без изменения кода. Одновременно это создает зависимость от корректной env-конфигурации: ошибка в TTL, DSN или лимитах меняет поведение всей системы сразу.

## 4. HTTP lifecycle

Полный путь обычного HTTP-запроса выглядит так:

1. Клиент обращается к `nginx`.
2. `nginx` направляет запрос в `public/index.php`.
3. Symfony Kernel поднимает контейнер сервисов.
4. API Platform сопоставляет URL с resource-классом из `src/UI/Controller`.
5. Для write-операций вызывается `Processor`, для read-операций `Provider`.
6. `Processor` или `Provider` работают только с DTO и use case/service слоя `Application`.
7. При необходимости `Application` вызывает репозитории доменного уровня, а конкретная реализация уходит в `Infrastructure`.
8. Ответ оборачивается в `ApiDataResponse`.
9. Ошибки нормализуются в единый JSON-формат через `ApiErrorNormalizer`.

Решение с API Platform как транспортным слоем выбрано правильно для CRUD-подобных и command-style endpoint'ов: маршрут, валидация input DTO и сериализация ответа формализованы в одном месте. Цена этого решения в том, что реальный runtime-поток распределен между resource, processor/provider, DTO и use case, и его сложнее читать, чем у обычного контроллера.

## 5. Слой Domain

### User

`User` является одновременно доменной сущностью и security user. Он хранит:

- UUID v7 идентификатор
- email
- password hash
- roles
- `isVerified`
- `lastLoginAt`
- `createdAt`

У сущности есть поведенческие методы:

- `verify()`
- `markLoggedIn()`
- `changePassword()`
- `changeRoles()`
- `changeEmail()`

Это хороший признак: модель не полностью анемична. Базовые инварианты по ролям и состоянию логина инкапсулированы внутри сущности.

### LoginToken

`LoginToken` хранит только:

- email
- hash токена
- время истечения
- признак использования через `usedAt`

Сырой токен не сохраняется в БД. Это сильное архитектурное решение: компрометация базы не раскрывает действующие login links.

### AccessToken

`AccessToken` связывает пользователя с hash bearer-токена и expiry timestamp. Raw token также не хранится.

### Email Value Object

`Email` инкапсулирует нормализацию и валидацию email на доменном уровне. Это снижает риск разнобоя форматов email между endpoint'ами и use case.

## 6. Слой Application

### Почему use case вынесены отдельно

Каждый важный бизнес-сценарий оформлен отдельным use case:

- `RequestLoginLinkUseCase`
- `ConfirmLoginTokenUseCase`
- `RegisterUserUseCase`

Это позволяет держать HTTP-детали вне core flow. Processor в UI лишь адаптирует transport к use case и переводит исключения в HTTP-статусы.

### DTO

DTO используются на входе и выходе API вместо прямой экспозиции Doctrine entities. Это дает:

- стабильный публичный контракт
- независимость API от внутренней схемы БД
- контроль над тем, какие поля доступны внешнему клиенту

Это одно из ключевых сильных решений в проекте.

### Прикладные сервисы

`TokenManager` отвечает за генерацию и SHA-256 hashing токенов.

`LoginRateLimiterService` использует Symfony RateLimiter поверх Redis.

`MultiAccountGuardService` контролирует:

- уникальность device fingerprint
- дневной лимит регистраций с IP
- синхронизацию registration context между PostgreSQL и Redis-backed policy state

`UserOutputMapper` преобразует доменные сущности в публичные output DTO.

Здесь важен компромисс: прикладные сервисы не содержат инфраструктурных деталей уровня SMTP или AMQP, но уже знают о cache semantics и anti-abuse политике. Это допустимо, потому что anti-abuse здесь относится к application policy, а не к чистой доменной логике.

## 7. Слой Infrastructure

### Doctrine

Doctrine используется как ORM и репозиторный backend. Маппинг настроен через PHP attributes, отдельно для:

- `src/Domain/Entity`
- `src/Infrastructure/Persistence/Doctrine/Entity`

Это соответствует DDD-подходу: бизнес-сущности живут в `Domain`, а вспомогательная persistence-модель `UserRegistrationContext` находится в `Infrastructure`.

### Redis

Redis подключен как:

- backend для `cache.app`
- backend для rate limiter

Это означает, что anti-abuse логика и кэш регистрационного контекста завязаны на один и тот же Redis instance. Для небольшого проекта это просто, но под нагрузкой создает shared bottleneck.

### RabbitMQ + Messenger

Email отправка идет через Symfony Messenger transport `async`. Сообщение сериализуется кастомным `JsonMessengerSerializer`, который намеренно не использует PHP-specific envelope-формат.

Это сильное решение по двум причинам:

- очередь становится language-agnostic
- формат сообщения проще для интеграции с внешними consumers

Минус: при добавлении новых типов сообщений serializer придется поддерживать вручную, иначе система начнет падать на encode/decode.

### Mail

`LoginLinkMailer` формирует HTML-письмо и отправляет его через Symfony Mailer. В dev-окружении письма попадают в Mailhog.

## 8. Слой UI

UI построен на API Platform resources:

- `POST /api/auth/register`
- `POST /api/auth/login-links`
- `POST /api/auth/request-login-link` как legacy alias
- `POST /api/auth/confirm-token`
- `GET /api/me`
- `GET/POST/PATCH/DELETE /api/admin/users`

Write-side выполняется через processors, read-side через providers. По сути это упрощенный CQRS на уровне HTTP-адаптера.

Это хорошее решение для читаемого разделения команд и чтения. Ограничение в том, что полноценной read-model нет: providers по-прежнему читают из тех же Doctrine сущностей и сервисов.

## 9. Жизненный цикл регистрации

### Шаги

1. Клиент вызывает `POST /api/auth/register`.
2. API Platform десериализует payload в `RegisterUserInput`.
3. `RegisterUserProcessor` получает IP клиента из `RequestStack`.
4. `RegisterUserUseCase` создает `Email` value object и проверяет уникальность email.
5. `MultiAccountGuardService::assertCanRegister()` проверяет:
   - занят ли device fingerprint в Redis
   - не превышен ли лимит регистраций с IP за сутки
6. В транзакции PostgreSQL:
   - создается `User`
   - пароль хешируется через argon2id
   - создается `LoginToken`
   - данные flush'атся в БД
7. После транзакции `MultiAccountGuardService::upsertRegistrationContext()`:
   - создает или обновляет `user_registration_context`
   - синхронизирует fingerprint и IP counters в Redis
8. В RabbitMQ отправляется `SendLoginLinkEmailMessage`.
9. Клиент сразу получает `UserOutput`.

### Почему поток разбит так

Создание `User` и `LoginToken` выполнено в транзакции, потому что эти данные логически образуют единый auth state. Это правильное решение.

Registration context и Redis синхронизация выполняются после транзакции пользователя. Вероятная причина: anti-abuse данные считаются вспомогательными и не должны усложнять core transaction.

### Узкое место

Здесь есть консистентностный разрыв:

- пользователь уже создан в PostgreSQL
- но обновление registration context или Redis может не выполниться

Следствие: система может частично зарегистрировать пользователя без полной anti-abuse фиксации. Для high-load и anti-fraud сценариев это слабое место.

## 10. Жизненный цикл запроса login link

### Шаги

1. Клиент вызывает `POST /api/auth/login-links`.
2. Payload превращается в `RequestLoginLinkInput`.
3. `RequestLoginLinkProcessor` вызывает `RequestLoginLinkUseCase`.
4. `LoginRateLimiterService` списывает 1 попытку из fixed-window limiter по email.
5. Система ищет пользователя по email.
6. Если пользователь не найден, use case завершает работу без ошибки.
7. Если пользователь найден:
   - генерируется raw token
   - в БД сохраняется только hash токена и TTL
   - в RabbitMQ публикуется сообщение на отправку письма
8. API немедленно отвечает `{"data":{"status":"ok"}}`.

### Почему это сделано так

Если email не найден, внешний ответ остается успешным. Это уменьшает user enumeration risk.

Email отправляется асинхронно, поэтому HTTP latency не зависит от SMTP.

### Узкие места

- Лимитер привязан только к email, не к IP и не к устройству. Это уменьшает точность anti-abuse модели.
- Fixed window проще, но хуже работает на границе окна, чем sliding window или token bucket.
- Токены копятся в `login_tokens`, а механизма фоновой очистки истекших токенов пока нет. На длинной эксплуатации таблица будет расти.

## 11. Жизненный цикл отправки email

### Шаги

1. `MessageBusInterface` диспатчит `SendLoginLinkEmailMessage`.
2. Messenger сериализует сообщение в JSON:
   - `type`
   - `payload`
3. RabbitMQ сохраняет сообщение.
4. Контейнер `worker` непрерывно исполняет `messenger:consume async`.
5. `SendLoginLinkEmailMessageHandler` получает сообщение.
6. `LoginLinkMailer` строит письмо и отправляет его через SMTP в Mailhog.

### Архитектурный смысл

Главный HTTP-путь не зависит от доступности SMTP-сервера и не ждет сетевой roundtrip на email.

### Узкие места

- В `docker-compose.yml` только один worker. При всплеске очереди email это становится bottleneck.
- Не видно отдельной retry/failure transport конфигурации. Если обработка письма упадет, поведение будет зависеть от дефолтных механизмов Messenger и может быть недостаточно прозрачным операционно.
- Не видно idempotency-guard на уровне обработчика. Повторная доставка сообщения может привести к повторной отправке письма.

## 12. Жизненный цикл подтверждения токена

### Шаги

1. Клиент вызывает `POST /api/auth/confirm-token` и передает email + raw token.
2. `ConfirmLoginTokenProcessor` запускает `ConfirmLoginTokenUseCase`.
3. Use case:
   - нормализует email
   - вычисляет SHA-256 hash токена
4. В транзакции PostgreSQL:
   - ищет пользователя по email
   - атомарно consume'ит login token через `UPDATE ... WHERE used_at IS NULL AND expires_at > :now`
   - обновляет `lastLoginAt`
   - проставляет `isVerified = true`
   - генерирует новый raw access token
   - сохраняет в БД только hash access token
   - flush'ит изменения
5. Клиент получает raw bearer token и timestamp истечения.

### Почему это сильное решение

Ключевой плюс здесь в атомарном consume токена. Вместо паттерна "прочитал токен -> проверил -> обновил" используется update с предикатами. Это резко снижает вероятность race condition при двойном подтверждении одного и того же токена.

### Узкие места

- Нет механизма ревокации старых access token пользователя при новом логине. Один пользователь может накопить несколько валидных токенов.
- Нет refresh token модели. При истечении access token клиенту нужно проходить passwordless flow заново.
- Проверка пользователя идет отдельно от consume токена. Это не критично, но добавляет еще один roundtrip к БД.

## 13. Жизненный цикл аутентифицированного запроса

### Шаги

1. Клиент отправляет `Authorization: Bearer <token>`.
2. `BearerTokenAuthenticator` проверяет наличие заголовка и извлекает raw token.
3. `TokenManager` вычисляет hash.
4. `AccessTokenRepository::findValidByHash()` ищет токен в PostgreSQL.
5. Если токен существует и не истек, в security context помещается связанный `User`.
6. Дальше выполняется provider или processor endpoint'а.

### Почему выбрана stateless-модель

Сессии отключены, firewall stateless. Это упрощает горизонтальное масштабирование HTTP-нод.

### Узкие места

- Каждая аутентифицированная операция бьет в PostgreSQL для lookup access token. Под высокой нагрузкой это будет один из главных hot path.
- Access token не кэшируется в Redis, хотя Redis уже есть в системе.
- `findValidByHash()` сначала находит запись, а истечение проверяет в PHP, а не целиком в SQL. Это не критично, но часть фильтрации вынесена из БД.

## 14. Жизненный цикл admin endpoint'ов

### Read path

`GET /api/admin/users` и `GET /api/admin/users/{id}` работают через providers. Они:

- читают пользователей из PostgreSQL
- для каждого пользователя подтягивают `UserRegistrationContext`
- маппят результат в admin DTO

### Write path

`POST`, `PATCH`, `DELETE` по `/api/admin/users` идут через processors и затем через доменные репозитории.

### Узкие места

- `AdminUsersProvider` сначала загружает всех пользователей, потом для каждого отдельно вызывает `findContext()`. Это потенциальный N+1 запрос.
- У списка пользователей отключена пагинация. На большом объеме это станет проблемой по памяти, latency и размеру ответа.
- В `PATCH` контракт на данный момент ограничен ролями. Это упрощает контроль изменений, но административная модель пока неполная.

## 15. Хранение данных и их роли

### PostgreSQL

PostgreSQL хранит системно значимые и долговременные данные:

- `users`
- `login_tokens`
- `access_tokens`
- `user_registration_context`

Это источник истины для authentication state.

### Redis

Redis хранит быстро меняющиеся anti-abuse и cache-данные:

- rate limiter keys по email
- fingerprint ownership
- IP registration counters

Redis здесь выступает как быстрый policy-store, а не как источник истины пользователей.

### RabbitMQ

RabbitMQ хранит промежуточные задачи на доставку email.

### Mailhog

Mailhog исключительно dev-инструмент. В production этот слой должен быть заменен на реальный SMTP/provider.

## 16. Почему архитектура в целом удачная

Сильные решения:

- четкое разделение `Domain / Application / Infrastructure / UI`
- DTO вместо экспозиции сущностей
- stateless auth
- хранение только hash токенов
- async email delivery
- вынесение anti-abuse контекста в отдельную таблицу
- use case как явные бизнес-сценарии
- attributes вместо legacy-конфигураций маппинга и API

Эта архитектура хорошо подходит для роста проекта: проще добавлять новые use case, новые transports и альтернативные инфраструктурные реализации.

## 17. Основные узкие места и риски

### 1. PostgreSQL как hot path для auth

Каждый `confirm-token`, `me` и любой защищенный endpoint завязан на lookup токенов и пользователей в PostgreSQL. При масштабировании это будет одна из первых точек давления.

Что можно улучшить:

- кэшировать access token lookup в Redis
- добавить write-through / cache-aside стратегию
- ввести revocation versioning per user

### 2. Рост таблиц токенов

`login_tokens` и `access_tokens` будут постоянно расти.

Что можно улучшить:

- cron или scheduled command на удаление истекших токенов
- партиционирование или архивирование для long-lived production

### 3. Разрыв консистентности между PostgreSQL и Redis

Регистрация сначала фиксируется в PostgreSQL, а затем anti-abuse контекст раскладывается по PostgreSQL auxiliary table и Redis.

Риск:

- при падении процесса между этими операциями anti-abuse след останется неполным

Что можно улучшить:

- transactional outbox
- post-commit event
- reconciliation job для self-healing

### 4. Один worker на очередь

Один consumer ограничивает throughput email-пайплайна.

Что можно улучшить:

- горизонтально масштабировать worker
- ввести separate failure transport
- настроить retry/backoff/prefetch

### 5. 


Админский список пользователей сейчас не рассчитан на большой объем данных.

Что можно улучшить:

- пагинация
- join/projection read model
- специализированный query service вместо последовательных lookup'ов

### 6. Redis как единая точка для cache и limiter

Один Redis instance обслуживает сразу limiter, anti-abuse cache и любые будущие app cache сценарии.

Риск:

- конкуренция за память
- eviction или деградация latency будет одновременно бить по нескольким критичным функциям

Что можно улучшить:

- разнести logical DB или отдельные Redis instances по ролям
- настроить memory policy осознанно

### 7. Долгосрочная эксплуатация passwordless без device/session management

Сейчас access token независимы друг от друга и не привязаны к устройству или сессии.

Риск:

- нет списка активных сессий
- нет logout/revoke сценария
- нет управления компрометированными токенами

Что можно улучшить:

- хранить device/session metadata
- реализовать revoke endpoint
- ограничить число активных токенов на пользователя

## 18. Что важно понимать при дальнейшем развитии

Если проект будет расти как high-load система, следующими кандидатами на усиление почти наверняка станут:

1. auth hot path и token lookup
2. cleanup-стратегия токенов
3. надежность async-пайплайна
4. read-model для admin API
5. более строгая консистентность anti-abuse данных

Текущая архитектура уже достаточно зрелая, чтобы развиваться без полного переписывания. Основные ограничения сейчас не в структуре слоев, а в операционных деталях масштабирования и в нескольких узких runtime-точках вокруг PostgreSQL, Redis и очереди.
