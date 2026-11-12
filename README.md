# Unitman

[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7-black)](https://symfony.com/)
[![RoadRunner](https://img.shields.io/badge/RoadRunner-HTTP%20%2B%20Jobs-yellowgreen)](https://roadrunner.dev/)
[![Temporal](https://img.shields.io/badge/Temporal-workflows-blue)](https://temporal.io/)

**Unitman** — сервис автоматического создания тестовых стендов («юнитов») для тестировщиков.
Он следит за ветками в GitLab/GitHub, сам собирает и запускает стенд под задачу, позволяет
настраивать параметры юнита и — главное — **направлять юниты друг на друга**, чтобы тестировать
кросс-сервисные задачи без релизов.

---

## Содержание

- [Зачем это нужно](#зачем-это-нужно)
- [Ключевые понятия](#ключевые-понятия)
- [Как это работает](#как-это-работает)
- [Технологический стек](#технологический-стек)
- [Архитектура](#архитектура)
- [Быстрый старт (Docker)](#быстрый-старт-docker)
- [Конфигурация окружения](#конфигурация-окружения)
- [Конфиг юнита `unitman.yaml`](#конфиг-юнита-unitmanyaml)
- [HTTP API](#http-api)
- [Разработка](#разработка)
- [Полезные команды](#полезные-команды)

---

## Зачем это нужно

Классическая проблема: задача затрагивает несколько сервисов. Пока доработки не готовы, их
приходится катить в релиз последовательно, тестируя «по шагам». Это растягивает разработку и
заставляет релизить незаконченные изменения.

Unitman решает это так:

1. Разработчик пушит ветку в GitLab/GitHub.
2. Unitman **автоматически** собирает и запускает юнит под эту ветку — к моменту, когда задача
   дошла до тестировщика, стенд уже готов.
3. Тестировщик может **направить один юнит на другой** (например, `orders` на свой юнит
   `dictionary`), тестируя всю цепочку сервисов сразу.
4. Ветки можно вести параллельно и **катить только полностью готовые** решения в релиз.

Дополнительные преимущества:

- Видно, **сколько времени** ветки задач висят в разработке/тестировании.
- **Автообновление** юнита, если разработчик пушит новый код, и **автоудаление**, когда ветка
  влита в релиз и удалена.
- Юнит можно кастомизировать: выполнять произвольные команды (нагрузочные/юнит-тесты), смотреть
  логи контейнеров, менять переменные окружения и т.д.

**Целевая аудитория:** IT-компании, занимающиеся разработкой ПО.

---

## Ключевые понятия

| Сущность | Где живёт | Описание |
| --- | --- | --- |
| **Project** | [`Project.php`](src/Unitman/Business/Model/Project.php) | Git-репозиторий, подключённый к системе. Хранит настройки хуков, переменные, участников, proxy host. |
| **Repo** | [`Repo.php`](src/Unitman/Business/Model/Repo.php) | Учётные данные для доступа к Git-хостингу (`GITLAB` / `GITHUB` / `BITBUCKET`). |
| **Unit** («юнит») | [`Unit.php`](src/Unitman/Business/Model/Unit.php) | Тестовый стенд, собранный из конкретной ветки проекта. Имеет состояние, конфиг, переменные, тома, процессы и задачи Runner'а. |
| **Runner** | [`src/Runner/`](src/Runner) | Исполнитель: собирает образы, поднимает/останавливает контейнеры, проверяет работоспособность. Общается с Golang-runner'ом через Temporal. |

Юнит — это конечный автомат. Основные состояния ([`Unit/State/`](src/Unitman/Business/Model/Unit/State)):
`Sozdan` → `VOcherediNaSborku` → `Sobran` → `VOcherediNaPodgotovku` → `Podgotovlen` →
`VOcherediNaZapusk` → `Zapushen`, плюс состояния ошибок (`OshibkaSborki`, `OshibkaZapuska`, …)
и удаления (`Udalen`, `UdalenVruchnuyu`). Переходы инициируются пользовательскими командами
(`sobrat`, `zapustit`, `ostanovit`, …) и результатами Runner'а.

---

## Как это работает

```
GitLab / GitHub                          Unitman                              Runner
      │                                     │                                    │
      │  push в ветку                       │                                    │
      ├──────────── webhook ───────────────►│                                    │
      │                                     │  UnitSozdan / SborkaUnitNachalas   │
      │                                     ├───────── Temporal workflow ────────►│
      │                                     │                                    │ clone / build
      │                                     │                                    │ compose up
      │                                     │◄──────── результат сборки ─────────┤
      │                                     │  Sobran → Podgotovlen → Zapushen   │
      │                                     │                                    │
      │  push в другую ветку                │  авто-обновление юнита             │
      ├──────────── webhook ───────────────►├───────────────────────────────────►│
      │                                     │                                    │
      │  ветка удалена после мержа          │  авто-удаление юнита               │
      ├──────────── webhook ───────────────►├───────────────────────────────────►│
```

Внутренние события агрегата (`UnitSbroshenDoSostoyaniyaSborki`, `UspehZapuskaUnitaUstanovlen`, …)
обрабатываются фоновыми задачами RoadRunner Jobs: очередь `sobitiya_iz_hranilisha` проигрывает
EventSauce-события в проекции, `websocket_unit_event` пушит обновления в Centrifugo,
`webhook_event` обрабатывает события Git-хуков.

---

## Технологический стек

- **PHP ≥ 8.2**, Symfony 7 (micro-kernel), `declare(strict_types=1)`, `final`-классы.
- **RoadRunner** — HTTP-сервер и Jobs-воркер (`.rr.yaml`, бандл `fluffydiscord/roadrunner-symfony-bundle`).
- **Temporal** — долгоживущие workflow/activity сборки и управления контейнерами (`temporal/sdk`).
- **EventSauce** — event sourcing (агрегаты, события, проекции, checkpoint-store).
- **Doctrine DBAL** — работа с БД **без ORM**: репозитории пишут SQL напрямую.
- **PostgreSQL 13** — основное хранилище (таблицы event store: `<module>_event_store`).
- **JWT** (`lexik/jwt-authentication-bundle`), **Centrifugo** — аутентификация и realtime.
- **MinIO** — объектное хранилище медиатеки (архивы томов юнитов).
- **Beanstalkd / Redis** — очереди задач и KV.
- Качество: **PHPUnit 11**, **Psalm 6** (level 1), **deptrac**, **modulite**.

---

## Архитектура

Модульный монолит: каждый модуль изолирован правилами deptrac и общается с другими только через
свой `Api` или собственный `Acl`-адаптер.

```
src/
  Account/         — пользователи: регистрация, роли, JWT, настройки
  Unitman/         — ядро: проекты, репо, юниты, webhook'и (основная бизнес-логика)
  Runner/          — сборка/запуск/управление контейнерами (Golang-runner + Temporal)
  BackgroundJob/   — фоновые задачи (очередь RoadRunner Jobs)
  Initialization/  — первичная инициализация системы (proxy host, параметры)
  Mediateka/       — файлы и архивы томов (MinIO)
  App/             — общая инфраструктура (workflow client, event-store декораторы)
  Utils/           — разделяемые утилиты (EventSauce-инфраструктура, конвертеры, Response)
```

Структура модуля:

```
Api/          — публичный API модуля (единственная точка входа для других модулей)
Acl/          — адаптеры к чужим модулям
Business/
  Command/    — DTO команд и запросов
  Model/      — агрегаты, Value Objects, Event/
  Port/       — интерфейсы репозиториев и внешних сервисов
  ReadModel/  — read-модели запросов
  UseCase/    — обработчики: XxxUseCase (команды), XxxQuery (запросы)
Infra/
  Adapter/    — реализации портов
  Controller/ — HTTP-контроллеры (#[Route])
  Repository/ — SQL-репозитории (Doctrine DBAL)
  Projection/ — проекции event sourcing
  Jobs/       — обработчики фоновых задач
  Workflow/   — Temporal workflow/activity (модуль Runner)
Migrations/   — миграции модуля
Tests/        — тесты модуля
di.php, routing.php, deptrac.yaml
```

Правила зависимостей (deptrac):

- `Account` и `BackgroundJob` ни от кого не зависят; `Runner` и `Unitman` зависят **только** от
  `BackgroundJob`.
- Внутри модуля: `Business` (домен) не зависит ни от чего, `Infra` зависит от `Business`,
  `Api`/`Acl` — от `Business` + `Infra`.
- Прямой импорт `Infra` чужого модуля **запрещён**.

---

## Быстрый старт (Docker)

### Требования

- Docker + Docker Compose (v2)
- Доступ к Docker-сокету (юниты поднимаются в контейнерах на хосте)
- Свободные порты: `8082` (API), `8000` (Centrifugo), `9000`/`9001` (MinIO),
  `5433` (Postgres), `8088` (Temporal UI), `2112`/`30000` (метрики)

### Запуск

```bash
# 1. Клонировать репозиторий и перейти в него
git clone <repo-url> unitman && cd unitman

# 2. Скопировать окружение
cp .env.dist .env
# при необходимости переопределить значения в .env.local (секреты не коммитим!)

# 3. Внешняя сеть, в которой живут все контейнеры
docker network create unitman_default

# 4. Поднять Temporal (отдельный стек)
make tl

# 5. Собрать и поднять приложение
make local
```

`make local` выполняет `docker compose up -d --build` и запускает Jobs-воркер
`php bin/console app:jobs start`.

После старта:

| Что | Адрес |
| --- | --- |
| API | `http://localhost:8082/api` |
| Swagger UI | `http://localhost:8082/api/doc` |
| Centrifugo | `http://localhost:8000` |
| MinIO Console | `http://localhost:9001` |
| Temporal UI | `http://localhost:8088` |

### Первичная инициализация

Внутри контейнера `web`:

```bash
# ключи JWT (если не были сгенерированы)
docker compose exec web php bin/console lexik:jwt:generate-keypair

# схема БД
docker compose exec web php bin/console doctrine:migrations:migrate

# системный аккаунт (ROLE_SYSTEM) и пересборка проекций
docker compose exec web php bin/console app:account:system_account
docker compose exec web php bin/console app:projections:rebuild all
```

### Остановка

```bash
make local-stop   # приложение
make tl-stop      # temporal
make stop-all     # всё
```

### MVP / прод-подобный режим

```bash
make mvp          # dc.mvp.yml: APP_ENV=prod, opcache, front-контейнер
make mvp-stop
```

---

## Конфигурация окружения

Все переменные описаны в [`.env.dist`](.env.dist). Копия — в `.env` (коммитится только
`.env.dist`; секреты — в `.env.local`). Основные группы:

```dotenv
# --- Приложение ---
APP_ENV=dev
APP_SECRET=change-me
DEFAULT_LOCALE=ru
APP_DEBUG=false

# --- Порты наружу ---
UNITMAN_API_PORT=8082
UNITMAN_FRONT_PORT=8083
UNITMAN_CENTRIFUGO_PORT=8000
TEMPORAL_UI_PORT=8088
TEMPORAL_DB_PORT=5435
DB_PORT=5433

# --- PostgreSQL ---
DB_NAME=unitman
DB_USER=asd
DB_PASS=123
DATABASE_URL="postgresql://${DB_USER}:${DB_PASS}@postgres:5432/${DB_NAME}?serverVersion=13&charset=utf8"

# --- Event store (EventSauce) ---
EVENT_STORE_DSN="${DATABASE_URL}"
EVENT_STORE_USER=${DB_USER}
EVENT_STORE_PASSWORD=${DB_PASS}

# --- JWT ---
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=change-me
REFRESH_TOKEN_TTL=2592000   # 30 дней

# --- Centrifugo (realtime) ---
CENTRIFUGO_API_KEY=secret
CENTRIFUGO_API_ENDPOINT=http://centrifugo:8000/api
CENTRIFUGO_SECRET=admin_secret

# --- MinIO / медиатека ---
MINIO_ROOT_USER=unitman
MINIO_ROOT_PASSWORD=change-me-in-env-local
MINIO_ENDPOINT=http://minio:9000
MINIO_PUBLIC_ENDPOINT=http://localhost:9000
MEDIATEKA_BUCKET=unitman-mediateka
MEDIATEKA_PRESIGN_TTL=3600
MEDIATEKA_MAX_FILE_SIZE=209715200

# --- Прочее ---
LOCK_DSN=flock
RR_RPC=tcp://127.0.0.1:6001
RR_HTTP_NUM_WORKERS=12
RR_JOBS_NUM_WORKERS=20
RR_TEMPORAL_NUM_WORKERS=12
MIN_FREE_MEMORY_PERCENT_FOR_RUN_UNIT=10
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

> JWT-ключи (`config/jwt/*.pem`), `var/`, `vendor/` и `.env` **не коммитятся**
> (см. `.rooignore`). В проде секреты храните в `.env.local` / секретах CI.

Приложения-сателлиты:

- **RoadRunner** — [`.rr.yaml`](.rr.yaml): HTTP, Temporal activities, Jobs (Beanstalk),
  service `unit_runner_jobs`, метрики на `:2112`.
- **Temporal** — [`config/workflows.php`](config/workflows.php): реестр workflow, обязательный
  для каждого нового workflow.
- **Docker** — [`docker-compose.yml`](docker-compose.yml) (local), [`dc.mvp.yml`](dc.mvp.yml),
  [`dc.temporal.yml`](dc.temporal.yml), [`dc.tools.yml`](dc.tools.yml); общие сервисы —
  [`common-services.yml`](common-services.yml).

---

## Конфиг юнита `unitman.yaml`

Файл **лежит в корне репозитория проекта** и читается из ветки, под которую создаётся юнит
(скачивается `GitlabAdapter`/`GithubAdapter`, разбирается
[`YamlParser`](src/Unitman/Infra/Adapter/YamlParser.php)). Он описывает, как собрать и поднять
стенд, какие переменные показать пользователю и какие действия ему доступны.

```yaml
prepare:
  - cp -f .env.runner .env

reset_prepare:
  - rm -f .env

up:
  - docker compose up -d --build

down:
  - docker compose down

services:
  web:
    ports:
      - port: 80
        type: http
```

Обязательны только `prepare`, `reset_prepare`, `up`, `down` и `services` (каждый — непустой);
`variables`, `actions`, `build` и `unitman_volumes` необязательны.

Полное руководство — все блоки конфига, типы переменных (`string`, `integer`, `float`,
`collection`), хелпер ссылки на другой юнит, `actions`, `build`, `unitman_volumes`, сводная
таблица валидации и коды ошибок `unit.config.*` — в
**[`docs/unitman-config.md`](docs/unitman-config.md)**.

Валидация конфига — в домене ([`ConfigUnita`](src/Unitman/Business/Model/Unit/ConfigUnita.php)).

---

## HTTP API

- Базовый префикс: `/api`, документация — Swagger UI `/api/doc`, OpenAPI-JSON —
  `/api/doc.json` (Nelmio API Doc, спека в репозитории — [`docs/openapi.json`](docs/openapi.json)).
- Успешные ответы — конверт `App\Utils\Model\Reponse\Response`:
  `{"status": "success", "data": ...}` ([`SuccessResponse`](src/Utils/Model/Reponse/SuccessResponse.php)),
  бизнес-отказы — `{"status": "fail", "data": ...}` ([`FailResponse`](src/Utils/Model/Reponse/FailResponse.php)),
  технические ошибки — `{"status": "error", "message": "...", "code": "..."}`
  ([`ErrorResponse`](src/Utils/Model/Reponse/ErrorResponse.php)).
- Авторизация — JWT: `POST /api/login` (`json_login`, поля `username`/`password`) → токен,
  далее заголовок `Authorization: Bearer <token>`. Refresh — `POST /api/account/refreshToken`.
- Права проверяются **внутри UseCase** через порт `SecurityService`, а не в контроллере.

Публичные эндпоинты (без JWT, см. [`config/packages/security.yaml`](config/packages/security.yaml)):

| Метод | Путь | Назначение |
| --- | --- | --- |
| `POST` | `/api/account/refreshToken` | Обновление токена |
| `POST` | `/api/runner/saveSteps` | Приём шагов задачи от Runner'а |
| `GET`  | `/api/initialization/list` | Параметры инициализации |
| `POST` | `/api/project/{id}/hook` | Webhook Git-хостинга |
| `GET`  | `/api/doc` | Swagger UI |

Вход (`POST /api/login`) обслуживается отдельным firewall'ом `login` (`json_login` +
`authentication_success_handler` Lexik), остальные `/api/*` — stateless JWT.

Основные группы (все требуют JWT):

| Префикс | Модуль | Примеры методов |
| --- | --- | --- |
| `/api/account` | Account | `register`, `changeMyPassword`, `poluchitVsehPolzovatelei`, `changeRoleByAdmin` |
| `/api/project` | Unitman | `list`, `add`, `my`, `build`, `branchesList`, `{id}/hook`, `statistikaPoProektam` |
| `/api/project/webhook` | Unitman | `dobavit`, `{id}/otkluchit`, `poluchitSobitiyaWebhooka` |
| `/api/repo` | Unitman | `add`, `list`, `confirm`, `changeCredentials` |
| `/api/unit` | Unitman | `sozdat`, `sobrat`, `zapustit`, `ostanovit`, `udalit`, `poluchitKonfigUnita`, `zapolnitPeremenie`, `vipolnitDeistviye` |
| `/api/volumeType` | Unitman | `poluchitTipyVolumov`, `dobavitTipVoluma`, `prikrepitFaylKTipuVoluma` |
| `/api/mediateka` | Mediateka | `upload`, `file/{id}`, `unit/{unitId}` |
| `/api/runner` | Runner | `getDefaultRunnerActive`, `saveSteps` |
| `/api/initialization` | Initialization | `proxy_host` (PUT), `list` |

Пример создания юнита:

```bash
curl -X POST http://localhost:8082/api/unit/sozdat \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
        "projectId": "services/orders",
        "unitName": "TASK-1234",
        "branch": "feature/TASK-1234",
        "memoryLimit": 1024,
        "znacheniePeremenoi": [
          {"id": "ORDER_SERVICE", "value": "master", "type": "string", "targetUnitId": "<unit-uuid>"}
        ],
        "volumes": [
          {"dir": "db", "name": "БД", "fileId": "<mediateka-file-uuid>"}
        ]
      }'
```

---

## Разработка

### Правила кода

- `declare(strict_types=1)`, классы `final`, свойства через constructor property promotion.
- Бизнес-логика — только в `Business/UseCase` и `Business/Model`, не в контроллерах и репозиториях.
- События — прошедшее время (`UnitSozdan`, `PasswordWasChanged`), команды — императив
  (`SozdatUnit`, `ZapustitUnit`).
- Наименования — смесь английского и транслита; следуйте стилю соседнего кода внутри фичи.
- Нельзя: создавать циклические зависимости между модулями, тянуть `Infra` чужого модуля,
  использовать ORM-сущности, переименовывать `App\Utils\Model\Reponse`.
- Новый модуль = папка с `di.php` + `routing.php` (автоподхват в [`src/Kernel.php`](src/Kernel.php)).

### Event sourcing

Агрегат реализует `AggregateRoot` + `AggregateRootBehaviour`; команды валидируют и вызывают
`recordThat(new Event(...))`; применение — приватный `apply<ИмяСобытия>()`. Проекции наследуют
`App\Utils\EventSauce\AbstractProjection`, тегируются `sync_projection` и регистрируются через
`tagged_iterator` в `di.php`. Event store — таблица `<module>_event_store`, сервисы `<module>.*`.
Эталон — [`src/Account/di.php`](src/Account/di.php).

### Temporal

Workflow — `src/Runner/Infra/Workflow/*Workflow.php` (`#[WorkflowInterface]`,
`#[WorkflowMethod]`), **обязательная** регистрация в [`config/workflows.php`](config/workflows.php).
Очередь — `WorkflowClientFactory::runnerQueueName`. Результаты активностей — DTO в
`Business/Model/GolangRunner/*`.

### Тесты и проверки

Все команды выполняются **внутри контейнера**, перед запуском — очистка кэша:

```bash
docker compose exec web php bin/console cache:clear

# точечно
docker compose exec web php vendor/bin/phpunit
docker compose exec web php vendor/bin/psalm
docker compose exec web php deptrac.phar

# всё сразу: psalm + phpunit + все deptrac
docker compose exec web composer run all-checks
```

- Тесты — в `src/<Module>/Tests` (suite `src/*/Tests`), bootstrap `tests/bootstrap.php`.
- Psalm level 1 ([`psalm.xml`](psalm.xml)); новые `@psalm-suppress` — только при необходимости.
- Покрытие считается по `src/*/Business/Model` — держите доменную логику там.
- После правок кода обязателен **перезапуск RoadRunner**: `rr reset`.

---

## Полезные команды

```bash
# Docker-стек
make local / local-start / local-stop     # dev-стек
make mvp   / mvp-start   / mvp-stop       # прод-подобный стек
make tl    / tl-stop                      # Temporal
make tools / tools-stop                   # инструменты
make stop-all                             # остановить всё
make update                               # git pull + миграции + рестарт jobs

# Внутри контейнера web
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair
php bin/console app:account:system_account      # системный аккаунт ROLE_SYSTEM
php bin/console app:projections:rebuild all     # пересобрать проекции
php bin/console app:projections:pull <name>     # догнать проекцию
php bin/console app:jobs start                  # Jobs-воркер (RoadRunner)
php bin/console app:service:start unit_runner_jobs

# OpenAPI
composer run openapi-dump    # перегенерировать docs/openapi.json
composer run check-openapi   # проверка соответствия документации

# Качество
composer run check-psalm
composer run check-phpunit
composer run check-deptrac
composer run check-unitman-deptrac / check-runner-deptrac / ...
```

---

## Лицензия

Proprietary. Все права защищены.
