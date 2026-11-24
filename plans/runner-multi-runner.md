# Multi-runner архитектура — контракты Wave 0

Статус: **контракты Wave 0 зафиксированы; интеграция/финализация (Wave 2)
выполнена.** Документ остаётся источником истины по регистрации, выбору,
привязке и build-state. Default runner, `Initialization` и backend-стадия
сборки проекта удалены; порядок выката см. §9.

## 1. Термины

- **runnerId** — UUID v4, генерируется backend-ом при регистрации раннера.
  Идентификатор раннера в реестре (`runner_state.id`) и одновременно имя
  Temporal task queue.
- **taskQueue** — строка Temporal, используемая при `ActivityOptions::withTaskQueue()`.
  Для multi-runner всегда равна `runnerId` (verbatim, без префиксов и хеширования).
- **registryHost** — публичный **host** реестра раннеров (bare host, без схемы
  и порта), который backend отдаёт раннеру при регистрации. Источник:
  `UNITMAN_REGISTRY_PUBLIC_ADDRESS`.
- **publicAddress** — публичный URL reverse-proxy/L4-прокси конкретного
  раннера. Задаётся администратором отдельно после регистрации. Default/
  fallback **запрещён**: если у раннера нет `publicAddress`, он не может быть
  выбран для операций, требующих публичного адреса (сборки, ссылки).
- **default runner** — legacy-раннер с фиксированной очередью
  `unitman-runner-queue`. **Удалён**: дефолтного раннера в продукте больше нет,
  очередь всегда берётся из `taskQueue` выбранного раннера.

## 2. Регистрация раннера и публичный адрес

### 2.1 `POST /api/runner/register`

Публичный эндпоинт (см. `config/packages/security.yaml`, `PUBLIC_ACCESS`):
у раннера ещё нет `runnerId` и он не является пользователем системы.
Bootstrap-подписи больше нет.

**Request** (`application/json`):

```json
{
  "hostname": "runner-1.example.com",
  "version": "1.4.2"
}
```

| Поле | Тип | Обяз. | Описание |
|------|-----|-------|----------|
| `hostname` | string | нет | Hostname/машиночитаемое имя раннера. |
| `version` | string | нет | Версия Go-раннера. |

**Response `201 Created`:**

```json
{
  "runnerId": "550e8400-e29b-41d4-a716-446655440000",
  "taskQueue": "550e8400-e29b-41d4-a716-446655440000",
  "secret": "<64 hex>",
  "registryHost": "registry.example.com",
  "ports": { "api": 8082, "temporal": 7233, "registry": 5000 }
}
```

`registryHost` — bare host без схемы и порта. Порты сервисов раннера
передаются отдельно в `ports`, а хосты сервисов выводятся из `registryHost`
и `ports`.

- `runnerId` — UUID v4, генерирует backend. Раннер использует его как
  `taskQueue` **как есть**.
- `taskQueue` — всегда строго равен `runnerId`.
- `secret = hash_hmac('sha256', runnerId, RUNNER_SECRET_MASTER_KEY ?: APP_SECRET)`
  — детерминированный, отдаётся один раз и **не сохраняется** в БД.
- `registryHost` — bare host из `UNITMAN_REGISTRY_PUBLIC_ADDRESS` (без схемы и
  порта).
- `ports` — `UNITMAN_API_PORT`, `UNITMAN_TEMPORAL_PUBLIC_PORT`,
  `UNITMAN_REGISTRY_PUBLIC_PORT` (дефолты 8082 / 7233 / 5000); хосты сервисов
  раннера выводятся из `registryHost` и `ports`.

**Ошибки:**

| HTTP | Код | Причина |
|------|-----|---------|
| 400 | `runner.registration.invalid_payload` | невалидный JSON |
| 500 | `runner.registration.failed` | внутренняя ошибка сохранения |

### 2.2 Сохранение состояния раннера

Backend создаёт `RunnerState`:

- `id` = `runnerId`;
- `taskQueue` = `runnerId`;
- `active` = `true`;
- `registryHost`, `hostname` — из регистрации;
- `registeredAt` = unix-время регистрации (legacy `lastRegisteredAt`
  сохраняется для обратной совместимости);
- `publicAddress` = `null` — задаётся администратором отдельно (§2.3).

Все поля nullable в `App\Runner\Business\Model\RunnerState`, поэтому старые
payload-ы без новых ключей гидратируются без ошибок.

### 2.3 Публичный адрес (только администратор)

| Метод | Путь | Ответ |
|-------|------|-------|
| `PUT` | `/api/runner/{runnerId}/public-address` | `{runnerId, publicAddress}` |
| `DELETE` | `/api/runner/{runnerId}/public-address` | `204 No Content` |
| `POST` | `/api/runner/{runnerId}/public-address/check` | `{reachable: bool, error?: string}` |

`PUT`/`check` принимают `{"publicAddress":"https://runner-1.example.com"}`.
Валидация: абсолютный URL, схема `http`/`https`, непустой host, без userinfo
(`user:pass@`). Неизвестный `runnerId` → `404 runner.not_found`; невалидный
адрес → `400 runner.public_address.invalid`.

Проверка доступности — best-effort `GET <publicAddress>/__unitman/health` с
коротким таймаутом (connect 2 с, запрос 3 с, максимум 5 с). Она консультативная:
недоступность возвращается как `{reachable:false,error}` и никогда не блокирует
сохранение; сама проверка ничего не сохраняет.

### 2.4 Список и правило выбора

`GET /api/runner/list` (только администратор) отдаёт для каждого раннера
`publicAddress`, `registeredAt`, `hasPublicAddress`; `secret` в списке
отсутствует.

`DefaultRunnerSelector` и `RunnerSelectionService::select()` исключают раннеры
с пустым `publicAddress`. Такой раннер может присылать `health` и остаётся в
реестре, но не выбирается для сборок и построения ссылок.

## 3. Состояние сборки проекта на раннере

Проект собирается **на каждом раннере отдельно**. Факт сборки фиксируется в
`project_build_state` и проверяется перед ресурсоёмкими операциями (позже — в
`NachatSborkuUnitaActivity`).

### 3.1 Проверка

`GET /api/runner/{runnerId}/projects/{projectId}/build-state`

**Response `200 OK`:**

```json
{
  "built": true,
  "builtAt": 1764000000,
  "commit": "a1b2c3d4",
  "branch": "main"
}
```

Если записи нет — `200 OK` с `"built": false` и `builtAt/commit/branch = null`
(отсутствие сборки — нормальное состояние, не ошибка).

**Ошибки:** `404 runner.not_found` — неизвестный `runnerId`;
`400 project_build_state.invalid_project_id`.

### 3.2 Запись

`PUT /api/runner/{runnerId}/projects/{projectId}/build-state`

**Request:**

```json
{
  "builtAt": 1764000000,
  "commit": "a1b2c3d4",
  "branch": "main"
}
```

`builtAt` необязателен (по умолчанию `time()`); `commit`/`branch` —
необязательные метаданные. Upsert по `(runner_id, project_id)`.

**Response `200 OK`** — сохранённое состояние в формате §3.1.

**Ошибки:** `404 runner.not_found`, `400 project_build_state.invalid_payload`.

**Аутентификация:** per-runner, как у остальных callback-ов раннера:
`X-Runner-Id: <runnerId>` + `X-Runner-Signature: hex(hmac_sha256(raw_body, secret))`,
где `secret` — детерминированный секрет из регистрации (§2.1). Неизвестный
`X-Runner-Id` → `404 runner.not_found`; неверная подпись → `401`. JWT и общий
глобальный секрет не используются. Внутренние сервисы backend-а могут вызывать
use case напрямую, минуя HTTP.

## 4. Формат `runnerId` / `taskQueue`

- `runnerId` — UUID v4 (canonical, lowercase, 36 символов), генерируется
  backend-ом.
- `taskQueue` — **verbatim** `runnerId`. Без `runner-` префикса, без
  нормализации регистра.
- Default runner и legacy-очередь `unitman-runner-queue` **удалены**.
- Очередь передаётся в workflow и далее в
  `ActivityOptions::withTaskQueue($taskQueue)`. Пустая строка — ошибка:
  workflow завершается fail-fast (`runner.task_queue.empty`), fallback нет.

## 5. Схема БД

Все timestamp-поля — unix-время в секундах (`integer`/`bigint`), как в
соседних миграциях (`opencode_workspace_session`, `unit_zanyatost`).

### 5.1 `unit_runner`

Жёсткая привязка юнита к раннеру.

```sql
create table if not exists unit_runner
(
    unit_id    varchar(64) not null primary key,
    runner_id  varchar(128) not null,
    created_at integer not null
);

create index if not exists unit_runner_runner_idx
    on unit_runner (runner_id);
```

- `unit_id` — PK. Юнит живёт в event store, отдельной таблицы `unit` нет,
  поэтому физический FK на `unit_id` невозможен; целостность обеспечивает
  прикладной слой.
- `runner_id` — логический FK на `runner_state(id)`; физический FK добавлен
  в `project_build_state` (см. §5.3). Для `unit_runner` FK не добавляется,
  чтобы не связывать привязки с жизненным циклом legacy `runner_state`.

### 5.2 `workspace_runner`

Привязка workspace к раннеру.

```sql
create table if not exists workspace_runner
(
    workspace_id varchar(64) not null primary key,
    runner_id    varchar(128) not null,
    created_at   integer not null
);

create index if not exists workspace_runner_runner_idx
    on workspace_runner (runner_id);
```

Аналогично `unit_id`: workspace-ы OpenCode не имеют таблицы с
`workspace_id` в качестве PK (`opencode_workspace_session.id` — id сессии),
поэтому физический FK не добавляется.

### 5.3 `project_build_state`

```sql
create table if not exists project_build_state
(
    runner_id  varchar(128) not null
        constraint project_build_state_runner_fk
            references runner_state (id) on delete cascade,
    project_id varchar(64) not null,
    built_at   integer not null,
    commit_sha varchar(64) default null,
    branch     varchar(256) default null,
    primary key (runner_id, project_id)
);

create index if not exists project_build_state_project_idx
    on project_build_state (project_id);
```

- Составной PK `(runner_id, project_id)`.
- `runner_id` → `runner_state(id)` с каскадным удалением.
- `commit_sha`/`branch` — необязательные метаданные сборки.

### 5.4 Удаление legacy `initialization`

```sql
DROP TABLE IF EXISTS initialization;
```

Таблица создавалась модулем `Initialization`
(`Version20260415000000`). Модуль `Initialization` **удалён** в Wave 2;
миграция удаления идемпотентна.

## 6. Выбор раннера

Порт `App\Runner\Business\Port\RunnerSelector`.

Правила:

1. Кандидат проходит фильтр: `active === true`, `isHealthFresh()` (TTL 180 с),
   `isResourcesBlocked() === false`.
2. Среди прошедших считается score (больше — лучше):
   - свободная память (`MemoryInfo::getFreePercent()`), вес 0.5;
   - свободный CPU (`100 - RunnerResources.cpu.usagePercent`), вес 0.3;
   - свободный диск / меньший Docker footprint, вес 0.2.
3. Tie-break: меньше привязанных юнитов (`unit_runner`), затем
   лексикографически меньший `runnerId` (детерминизм).
4. Если живых раннеров нет — `DomainException('runner.not_found')`.

## 7. Привязка юнитов и workspace

- Юнит жёстко привязан к раннеру через `unit_runner`. Автоматической миграции
  юнита на другой раннер **нет**.
- Workspace жёстко привязан к раннеру через `workspace_runner`.
- Если привязанный раннер неактивен/не найден — операция завершается ошибкой
  (`runner.not_found` для отсутствующего, `runner.inactive` для неактивного);
  юнит/workspace не переносится.
- Проект собирается per-runner; перед использованием проверяется
  `project_build_state`, после успешной сборки запись создаётся/обновляется.
- Значение `publicAddress` берётся только из реестра для выбранного `runnerId`.
  Fallback на общий/default адрес **запрещён**.

## 8. Порты Wave 0

| Порт | Модуль | Назначение |
|------|--------|------------|
| `RunnerSelector` | `Runner\Business\Port` | выбор живого раннера |
| `ProjectBuildStateRepository` | `Runner\Business\Port` | чтение/запись `project_build_state` |
| `UnitRunnerRepository` | `Unitman\Business\Port\Unit` | привязка юнита к раннеру |
| `WorkspaceRunnerRepository` | `OpenCode\Business\Port` | привязка workspace к раннеру |
| `RunnerPublicAddressProvider` | `Unitman\Business\Port` | публичный адрес по `runnerId` (без fallback) |

Реализации — минимальные рабочие/stub-версии, достаточные для компиляции и
boot контейнера. Регистрация раннера, фактическая выдача `runnerId` и полная
интеграция с workflow — следующие волны.

## 9. Порядок раскатки (rollout order)

1. **Миграции.** Применить новые миграции backend-а:
   `php bin/console doctrine:migrations:migrate -n`.
   Появляются таблицы `project_build_state`, `unit_runner`, `workspace_runner`;
   legacy-таблица `initialization` удаляется. Миграция
   `Version20240415170449` — документированный no-op (дефолтный раннер удалён).
2. **Реестр и секреты backend-а.** Поднять `unitman-registry` (том
   `unitman-registry-data`), задать `UNITMAN_REGISTRY_PUBLIC_ADDRESS` (адрес,
   который увидят раннеры), `UNITMAN_TEMPORAL_PUBLIC_PORT` и
   `RUNNER_SECRET_MASTER_KEY` (fallback — `APP_SECRET`). Отдельных глобальных
   секретов регистрации/callback нет: используется per-runner схема.
3. **Перезапуск backend-воркеров.** Пересобрать/перезапустить `web`,
   `web-worker`, `web-temporal` (RoadRunner роли). Backend-стадия сборки
   проекта и `Initialization` удалены: эндпоинтов `/api/project/build` и
   `/api/initialization/*` больше нет.
4. **Регистрация раннеров.** На каждом раннере выполнить регистрацию
   (`POST /api/runner/register`). В `config.json` записываются `runnerId`,
   `taskQueue` (= `runnerId`), `registryHost`, `ports` и полученный `secret`;
   backend секрет не персистит. Публичный адрес задаётся администратором
   отдельно (`PUT /api/runner/{runnerId}/public-address`).
5. **Запуск раннеров.** Каждый раннер слушает собственную очередь
   `taskQueue`; общих/дефолтных очередей нет.
6. **Вывод старой очереди.** После перевода трафика остановить Temporal
   workers и workflow-диспетчеризацию на legacy `unitman-runner-queue`
   (старый `unitman-runner-queue` больше не используется ни в коде, ни в
   конфигурации; остатки — только в исторических документах),
   затем вывести из эксплуатации старый default-раннер.
7. **Ручная уборка.** Каталог `register_data/` (legacy-данные реестра, ~1.1G)
   не трогается автоматикой и удаляется вручную. Остановленный контейнер
   `unitman-runner-unitman-registry-1` также удаляется вручную после
   переключения на backend-реестр.
