# Workspace: зафиксированные контракты для параллельной разработки

## 0. Назначение и правила

Этот файл — **единственный источник истины** для субагентов. Каждый поток
читает только свой раздел и файлы из своей зоны ответственности. Смежные
модули и Go-репозиторий читать запрещено, если они не входят в зону потока.

Изменять контракт может только Architect. Если контракт кажется
противоречивым, субагент не правит его сам, а возвращает вопрос Architect.

## 1. Потоки работ и зоны ответственности

| Поток | Что делает | Может читать | Запрещено читать |
|---|---|---|---|
| A. OpenCode domain | миграция, модели, репозиторий сессий | `src/OpenCode/Business/Model`, `src/OpenCode/Business/Port/SessiyaOpenCodeRepository.php`, `src/OpenCode/Infra/Repository/SqlSessiyaOpenCodeRepository.php`, `src/OpenCode/Migrations`, `src/OpenCode/Tests/Business/Model`, `src/OpenCode/Tests/Infra/Repository`, этот файл | `src/Unitman`, `src/Runner`, `src/Account`, Go-репозиторий |
| B. Unitman project data | read-модель проекта, `canPush`, доступ, URL клона, `UnitmanApi` | `src/Unitman/Business/ReadModel/Project`, `src/Unitman/Business/Port/Project`, `src/Unitman/Business/Port/Repo`, `src/Unitman/Infra/Adapter/StorageApiAdapter*`, `src/Unitman/Business/UseCase/Project`, `src/Unitman/Api/UnitmanApi.php`, `src/Unitman/Tests`, этот файл | `src/OpenCode`, `src/Runner`, Go-репозиторий |
| C. Runner PHP | DTO команд/результатов, `RunnerApi`, workflow, ACL Runner | `src/Runner`, `config/workflows.php`, `src/Runner/Tests`, этот файл | `src/OpenCode`, `src/Unitman` кроме контракта `UnitmanApi`, Go-репозиторий |
| D. Go runner | образ workspace, compose, скрипты, активности, CLI executor | `../../go/src/unitman-runner`, этот файл | весь PHP `src/` |
| E. OpenCode application | порты, ACL, use cases, гейт, launcher, уведомления, controller, background job, OpenAPI, переводы, тесты | `src/OpenCode` (файлы потока A — только чтение публичного API), публичные API `UnitmanApi` и `RunnerApiInterface` + `src/Runner/Business/Command/Workspace/*` — только чтение, `src/App`, `src/Utils`, `translations`, `docs/openapi.json`, `config/packages/nelmio_api_doc.yaml`, этот файл | внутренности `src/Unitman` и `src/Runner` кроме публичных API, Go-репозиторий |
| F. Интеграция | документация, полный прогон, исправление стыков | всё, но только после слияния A-E | — |

Потоки A, B, C, D, E можно запускать параллельно. E компилируется только на
контрактах A/B/C; полный прогон тестов E выполняет после слияния A/B/C.

## 2. Контракт БД

### 2.1 `opencode_session`

```sql
alter table opencode_session
    add column if not exists tip varchar(16) not null default 'unit';

create index if not exists opencode_session_account_tip_idx
    on opencode_session (account_id, tip, updated_at);
```

### 2.2 `opencode_workspace`

```sql
create table if not exists opencode_workspace (
    session_id                  varchar(64)  not null primary key,
    branch                      varchar(256) not null,
    status                      varchar(32)  not null,
    read_only                   boolean      not null default false,
    initial_prompt              text         not null,
    initial_prompt_started_at   integer      default null,
    error_key                   varchar(128) default null,
    operation                   varchar(32)  not null,
    operation_workflow_id       varchar(64)  not null,
    operation_attempts          integer      not null default 0,
    next_attempt_at             integer      not null default 0,
    created_at                  integer      not null,
    updated_at                  integer      not null
);
create index if not exists opencode_workspace_status_idx
    on opencode_workspace (status, next_attempt_at);
```

### 2.3 `opencode_workspace_project`

```sql
create table if not exists opencode_workspace_project (
    session_id      varchar(64)  not null,
    project_id      varchar(64)  not null,
    project_name    varchar(256) not null,
    project_code    varchar(512) not null,
    can_push        boolean      not null default false,
    position_index  integer      not null default 0,
    created_at      integer      not null,
    primary key (session_id, project_id)
);
create index if not exists opencode_workspace_project_session_idx
    on opencode_workspace_project (session_id, position_index);
```

### 2.4 `opencode_notification`

```sql
alter table opencode_notification
    add column if not exists session_type varchar(16) not null default 'unit';
```

### 2.5 Уникальность имени workspace

```sql
create unique index if not exists opencode_workspace_name_uq
    on opencode_session (account_id, title)
    where tip = 'workspace';
```

## 3. Контракт A: OpenCode domain и репозиторий

### 3.1 Enum `TipSessiiOpenCode`

`src/OpenCode/Business/Model/TipSessiiOpenCode.php`:

```php
enum TipSessiiOpenCode: string
{
    case UNIT = 'unit';
    case WORKSPACE = 'workspace';
}
```

### 3.2 Enum `StatusWorkspace`

`src/OpenCode/Business/Model/StatusWorkspace.php`:

```php
enum StatusWorkspace: string
{
    case CREATING = 'CREATING';
    case STARTING = 'STARTING';
    case RUNNING = 'RUNNING';
    case ARCHIVING = 'ARCHIVING';
    case ARCHIVED = 'ARCHIVED';
    case DELETING = 'DELETING';
    case ERROR = 'ERROR';

    public function mozhnoPerevestiV(self $target): bool;
    public static function izStroki(string $value): self;
}
```

Допустимые переходы:

```text
CREATING -> RUNNING | ERROR
RUNNING -> ARCHIVING | DELETING
ARCHIVING -> ARCHIVED | ERROR
ARCHIVED -> STARTING | DELETING
STARTING -> RUNNING | ERROR
ERROR -> STARTING | DELETING
DELETING -> DELETING
```

### 3.3 `ProektWorkspace`

`src/OpenCode/Business/Model/ProektWorkspace.php`:

```php
final class ProektWorkspace
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $projectCode,
        public readonly bool $canPush,
        public readonly int $positionIndex,
    ) {}
}
```

### 3.4 `Workspace`

`src/OpenCode/Business/Model/Workspace.php`:

```php
final class Workspace
{
    public const OPERATION_CREATE = 'create';
    public const OPERATION_START = 'start';
    public const OPERATION_STOP = 'stop';
    public const OPERATION_DELETE = 'delete';

    /** @param list<ProektWorkspace> $projects */
    public static function create(
        string $branch,
        string $initialPrompt,
        array $projects,
        int $now,
    ): self;

    /** @param list<ProektWorkspace> $projects */
    public static function restore(
        string $branch,
        string $initialPrompt,
        array $projects,
        StatusWorkspace $status,
        bool $readOnly,
        ?string $errorKey,
        string $operation,
        string $operationWorkflowId,
        int $operationAttempts,
        int $nextAttemptAt,
        ?int $initialPromptStartedAt,
        int $createdAt,
        int $updatedAt,
    ): self;

    public function getBranch(): string;
    public function getInitialPrompt(): string;
    /** @return list<ProektWorkspace> */
    public function getProjects(): array;
    public function getStatus(): StatusWorkspace;
    public function isReadOnly(): bool;
    public function getErrorKey(): ?string;
    public function getOperation(): string;
    public function getOperationWorkflowId(): string;
    public function getOperationAttempts(): int;
    public function getNextAttemptAt(): int;
    public function getInitialPromptStartedAt(): ?int;
    public function getCreatedAt(): int;
    public function getUpdatedAt(): int;

    public function perevestiV(StatusWorkspace $status, int $now): void;
    public function zapomnitOperaciyu(string $operation, string $workflowId, int $now): void;
    public function otmetitOshibku(string $errorKey, int $now): void;
    public function zapomnitPopitku(int $attempts, int $nextAttemptAt, int $now): void;
    public function otmetitStartPervogoPrompta(int $now): void;
}
```

Инварианты:

- `branch` и `initialPrompt` не пустые;
- `projects` не пустой, уникален по `projectId`;
- `readOnly = true`, если хотя бы у одного проекта `canPush = false`;
- `status` и `operation` восстанавливаются из БД без молчаливой замены.

### 3.5 `SessiyaOpenCode`

Добавить:

```php
public const MAX_WORKSPACE_PROJECTS = 20;

/** @param list<ProektWorkspace> $projects */
public static function createWorkspace(
    string $id,
    string $accountId,
    string $credentialId,
    string $name,
    string $branch,
    array $projects,
    string $initialPrompt,
    int $now,
): self;

public static function restoreWorkspace(
    string $id,
    string $accountId,
    string $credentialId,
    string $name,
    ?string $externalSessionId,
    ?int $archivedAt,
    ?string $archiveReason,
    Workspace $workspace,
    int $createdAt,
    int $updatedAt,
): self;

public function getTip(): TipSessiiOpenCode;
public function estWorkspace(): bool;
public function getWorkspace(): ?Workspace;

/** Снимает архив после успешного восстановления. */
public function snovaAktivna(int $now): void;
```

Инварианты:

- workspace-сессия не имеет юнитов;
- `unitId`/`unitProjectId` у workspace всегда `null`;
- `title` workspace — это название workspace;
- `podpisatPoPromptu()` не перезаписывает название workspace;
- `arhivirovat()` для workspace ставит `archived_at` и `archive_reason`, как
  у обычной сессии;
- `snovaAktivna()` очищает `archived_at` и `archive_reason`.

### 3.6 `SessiyaOpenCodeRepository`

`src/OpenCode/Business/Port/SessiyaOpenCodeRepository.php`:

```php
public function save(SessiyaOpenCode $session): void;
public function update(SessiyaOpenCode $session): void;
public function getById(string $id): SessiyaOpenCode;
public function findById(string $id): ?SessiyaOpenCode;
public function findByAccountId(string $accountId, SessiyaOpenCodeFilter $filter): array;

/** @return list<SessiyaOpenCode> */
public function findAktivnieWorkspace(int $limit): array;

/**
 * Атомарный переход статуса. Возвращает true, если строка была обновлена.
 *
 * @param list<StatusWorkspace> $from
 */
public function perevestiStatusWorkspace(
    string $sessionId,
    array $from,
    StatusWorkspace $to,
): bool;

/** Полное удаление workspace: проекты, workspace, ходы, уведомления, сессия. */
public function deleteWorkspace(string $sessionId): void;
```

`SessiyaOpenCodeFilter` получает `?TipSessiiOpenCode $type`.

`SqlSessiyaOpenCodeRepository`:

- пишет `opencode_session`, `opencode_workspace`,
  `opencode_workspace_project` в одной транзакции;
- читает workspace и проекты batch-запросами без N+1;
- `findByAccountId` фильтрует по `tip`, если `type` задан;
- `perevestiStatusWorkspace` использует
  `update opencode_workspace set status = :to, updated_at = :now where session_id = :id and status = any(:from)`;
- `deleteWorkspace` удаляет в одной транзакции:
  `opencode_workspace_project`, `opencode_workspace`, `opencode_turn`,
  `opencode_notification`, `opencode_session`.

## 4. Контракт B: Unitman project data

### 4.1 Read-модель

`src/Unitman/Business/ReadModel/Project/ProektDlyaWorkspaceReadModel.php`:

```php
final class ProektDlyaWorkspaceReadModel
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $projectCode,
        public readonly ?string $proxyHost,
        public readonly bool $canPush,
        public readonly string $storageUrl,
        public readonly int $idePort,
    ) {}
}
```

`storageUrl` — URL с токеном. Наружу через HTTP OpenCode он не отдаётся.

### 4.2 `UnitmanApi`

Добавить:

```php
/**
 * @param list<string> $projectIds
 * @return list<ProektDlyaWorkspaceReadModel>
 * @throws \DomainException opencode.workspace.project_not_found
 */
public function poluchitProektiDlyaWorkspace(
    array $projectIds,
    string $accountId,
): array;

/**
 * URL клонирования с токеном. Только для Runner.
 * @throws \DomainException opencode.workspace.project_not_found
 */
public function poluchitUrlKlonaProekta(string $projectId): string;
```

`poluchitProektiDlyaWorkspace`:

1. берёт доступные пользователю проекты через
   `UmeetPoluchatSpisokProektovDlyPolzovatelya`;
2. сохраняет порядок `projectIds`;
3. дубликат проекта — `opencode.workspace.project_conflict`;
4. недоступный проект — `opencode.workspace.project_not_found`;
5. `canPush` вычисляет адаптер хостинга;
6. `idePort` — `IdePort::izOkruzheniya()`.

### 4.3 Проверка push

Добавить в `StorageApiAdapter`:

```php
public function canPush(Repo $repo, Project $project): bool;
```

GitLab: `permissions.project_access.access_level >= 30` или
`permissions.group_access.access_level >= 30`.

GitHub: `permissions.push === true`.

## 5. Контракт C: Runner PHP

### 5.1 Команды

Публичный запрос в Runner **не содержит токен**. Токенизированный `StorageUrl`
добавляет сам `RunnerApi` через `Runner\Acl\UnitmanAdapter`, поэтому OpenCode
никогда не видит токен.

`src/Runner/Business/Command/Workspace/ZapustitWorkspaceZapros.php`:

```php
final class ZapustitWorkspaceZapros
{
    /** @param list<ProektWorkspaceZapros> $Projects */
    public function __construct(
        public readonly string $WorkspaceId,
        public readonly string $WorkspaceName,
        public readonly string $WorkspaceSlug,
        public readonly string $Branch,
        public readonly bool $ReadOnly,
        public readonly int $MemoryLimit,
        public readonly array $Projects,
    ) {}
}
```

`ProektWorkspaceZapros`:

```php
final class ProektWorkspaceZapros
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $ProjectName,
        public readonly string $ProjectCode,
        public readonly bool $CanPush,
    ) {}
}
```

`src/Runner/Business/Command/Workspace/ZapustitWorkspace.php` — команда
активности, уже с токеном:

```php
final class ZapustitWorkspace
{
    /** @param list<ProektWorkspaceCommand> $Projects */
    public function __construct(
        public readonly string $WorkspaceId,
        public readonly string $WorkspaceName,
        public readonly string $WorkspaceSlug,
        public readonly string $Branch,
        public readonly bool $ReadOnly,
        public readonly int $MemoryLimit,
        public readonly array $Projects,
    ) {}
}
```

`ProektWorkspaceCommand`:

```php
final class ProektWorkspaceCommand
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $ProjectName,
        public readonly string $ProjectCode,
        public readonly string $StorageUrl,
        public readonly bool $CanPush,
    ) {}
}
```

`OstanovitWorkspace`:

```php
final class OstanovitWorkspace
{
    public function __construct(public readonly string $WorkspaceId) {}
}
```

`UdalitWorkspace`:

```php
final class UdalitWorkspace
{
    public function __construct(public readonly string $WorkspaceId) {}
}
```

`ProveritIzmeneniyaWorkspace`:

```php
final class ProveritIzmeneniyaWorkspace
{
    /** @param list<array{ProjectId: string, ProjectName: string}> $Projects */
    public function __construct(
        public readonly string $WorkspaceId,
        public readonly array $Projects,
    ) {}
}
```

### 5.2 Результаты

`ResultatZapuskaWorkspace`:

```php
final class ResultatZapuskaWorkspace
{
    /** @param list<array{ProjectId: string, Name: string, Success: bool, ErrorKey: string|null}> $Projects */
    public function __construct(
        public int $Success,
        public array $Steps = [],
        public string $ResponseId = '',
        public array $Projects = [],
    ) {}
}
```

`ResultatOstanovkiWorkspace`, `ResultatUdaleniyaWorkspace`:

```php
public int $Success;
public array $Steps;
public string $ResponseId;
```

`ResultatProverkiIzmeneniyWorkspace`:

```php
final class ResultatProverkiIzmeneniyWorkspace
{
    /**
     * @param list<array{ProjectId: string, Name: string, Dirty: bool, Files: list<string>}> $Projects
     */
    public function __construct(
        public bool $Success,
        public array $Projects = [],
    ) {}
}
```

### 5.3 `RunnerApiInterface`

Добавить:

```php
public function zapustitWorkspace(string $workflowId, ZapustitWorkspaceZapros $command): string;
public function poluchitResultatZapuskaWorkspace(string $workflowId): ?ResultatZapuskaWorkspace;

public function ostanovitWorkspace(string $workflowId, OstanovitWorkspace $command): string;
public function poluchitResultatOstanovkiWorkspace(string $workflowId): ?ResultatOstanovkiWorkspace;

public function udalitWorkspace(string $workflowId, UdalitWorkspace $command): string;
public function poluchitResultatUdaleniyaWorkspace(string $workflowId): ?ResultatUdaleniyaWorkspace;

public function proveritIzmeneniyaWorkspace(
    ProveritIzmeneniyaWorkspace $command,
): ResultatProverkiIzmeneniyWorkspace;
```

> **Обязательное следствие для потоков B/C/F.** Вместе с расширением
> `RunnerApiInterface` поток C обязан обновить тестовую реализацию
> `src/Unitman/Acl/MemoryRunnerService` (`#[When(env: 'test')]`, поток B) или
> сделать это в потоке F при интеграции: добавить все семь workspace-методов
> (`zapustitWorkspace`, `poluchitResultatZapuskaWorkspace`, `ostanovitWorkspace`,
> `poluchitResultatOstanovkiWorkspace`, `udalitWorkspace`,
> `poluchitResultatUdaleniyaWorkspace`, `proveritIzmeneniyaWorkspace`) с
> безопасными заглушками. Пока стаб не обновлён, test-ядро не компилируется:
> падают `cache:clear`, `app:translations:audit`, `nelmio:apidoc:dump` и
> `WebTestCase`. Поток E этот файл не меняет.

`RunnerApi::zapustitWorkspace`:

1. получает URL клона для каждого проекта через новый порт
   `Runner\Business\Port\UmeetPoluchatUrlKlonaProekta`;
2. реализация порта — `Runner\Acl\UnitmanAdapter`, внутри вызывает
   `UnitmanApi::poluchitUrlKlonaProekta($projectId)`;
3. собирает `ZapustitWorkspace` с токенизированными `StorageUrl`;
4. регистрирует progress token;
5. стартует workflow на `monoQueue` с `workflowId`.

Доступ к проектам уже проверил OpenCode через
`UnitmanApi::poluchitProektiDlyaWorkspace`; Runner повторно доступ не
проверяет, а только получает токенизированный URL для уже разрешённых
проектов.

`ZapustitWorkspaceZapros` не содержит `StorageUrl` и не может утечь токеном в
OpenCode. `ProektWorkspaceZapros` содержит только `ProjectId`,
`ProjectName`, `ProjectCode`, `CanPush`.

### 5.4 Workflow и имена активностей

PHP workflow:

- `ZapustitWorkspaceWorkflow`;
- `OstanovitWorkspaceWorkflow`;
- `UdalitWorkspaceWorkflow`;
- `ProveritIzmeneniyaWorkspaceWorkflow`.

Имена активностей Go:

- `ZapustitWorkspaceActivity`;
- `OstanovitWorkspaceActivity`;
- `UdalitWorkspaceActivity`;
- `ProveritIzmeneniyaWorkspaceActivity`.

Очередь: `unitman-runner-queue`.

`ProveritIzmeneniyaWorkspaceWorkflow` — синхронный workflow по образцу
`ProveritKonteinerUnitaWorkflow`, таймаут 30 секунд.

### 5.5 Расширение `NachatHodOpenCode`

Добавить в `NachatHodOpenCode` и `VipolnitHodOpenCode`:

```php
public readonly ?string $WorkspaceId = null;
public readonly bool $WorkspaceReadOnly = false;
public readonly array $WorkspaceProjects = [];
```

`WorkspaceProjects`: `list<array{ProjectId: string, ProjectName: string, ProjectCode: string, CanPush: bool}>`.

## 6. Контракт D: Go runner

### 6.1 Раскладка

```text
<RUNNER_WORK_DIR>/workspaces/<WorkspaceId>/
  Dockerfile
  compose.yaml
  .env
  seed/
  entrypoint.sh
  workspace-repo-sync.sh
  ide/
```

Внутри `/workspace` проекта:

```text
/workspace/<ProjectId>/
```

Каталоги проектов именуются по `ProjectId`, а человекочитаемое имя проекта
попадает в `/workspace/workspace.code-workspace` как `name` папки. Это
гарантирует уникальность каталогов без коллизий имён и не требует отдельного
`ProjectDir`-поля в контракте.

Именованные тома:

```text
workspace_<WorkspaceId>_app
workspace_<WorkspaceId>_docker
workspace_<WorkspaceId>_opencode
```

### 6.2 Переменные окружения workspace

```text
UNITMAN_WORKSPACE_ID
UNITMAN_WORKSPACE_NAME
UNITMAN_WORKSPACE_SLUG
UNITMAN_BRANCH_NAME
UNITMAN_READ_ONLY
UNITMAN_MEMORY_LIMIT
COMPOSE_PROJECT_NAME
UNITMAN_WORKSPACE_APP_VOLUME
UNITMAN_WORKSPACE_DOCKER_VOLUME
UNITMAN_WORKSPACE_OPENCODE_VOLUME
```

`container_name` workspace: `${UNITMAN_WORKSPACE_SLUG}.workspaces`.

### 6.3 Вход `ZapustitWorkspaceActivity`

```json
{
  "WorkspaceId": "uuid",
  "WorkspaceName": "payments-refactor",
  "WorkspaceSlug": "payments-refactor-1a2b3c4d",
  "Branch": "feature/payments",
  "ReadOnly": false,
  "MemoryLimit": 3072,
  "Projects": [
    {
      "ProjectId": "uuid",
      "ProjectName": "api",
      "ProjectCode": "services/api",
      "StorageUrl": "https://oauth2:TOKEN@gitlab.example/services/api.git",
      "CanPush": true
    }
  ]
}
```

### 6.4 Выход `ZapustitWorkspaceActivity`

```json
{
  "Success": 1,
  "Steps": [],
  "ResponseId": "workflow-id",
  "Projects": [
    {
      "ProjectId": "uuid",
      "Name": "api",
      "Success": true,
      "ErrorKey": null
    }
  ]
}
```

### 6.5 Остальные активности

`OstanovitWorkspaceActivity`:

```json
{ "WorkspaceId": "uuid" }
```

→ `{ "Success": 1, "Steps": [], "ResponseId": "workflow-id" }`.

`ProveritIzmeneniyaWorkspaceActivity`:

```json
{
  "WorkspaceId": "uuid",
  "Projects": [
    { "ProjectId": "uuid", "ProjectName": "api" }
  ]
}
```

→

```json
{
  "Success": true,
  "Projects": [
    { "ProjectId": "uuid", "Name": "api", "Dirty": true, "Files": [" M src/Foo.php"] }
  ]
}
```

`UdalitWorkspaceActivity`:

```json
{ "WorkspaceId": "uuid" }
```

→ `{ "Success": 1, "Steps": [], "ResponseId": "workflow-id" }`.

### 6.6 Семантика операций

- `ZapustitWorkspaceActivity` идемпотентна: существующие каталоги обновляются,
  проекты повторно не пересоздаются;
- `OstanovitWorkspaceActivity` — только `docker compose stop`;
- `UdalitWorkspaceActivity` — `docker compose down -v --remove-orphans` и
  удаление каталога;
- `ProveritIzmeneniyaWorkspaceActivity` — `git status --porcelain` в каждом
  проекте;
- cleanup хостового Docker не должен выполнять `container prune`,
  `volume prune`, `system prune` и `--volumes`.

### 6.7 OpenCode CLI в workspace

`HodCommand` получает:

```go
WorkspaceID       string
WorkspaceProjects []WorkspaceProject
WorkspaceReadOnly bool
```

`WorkspaceProject`: `ProjectId`, `ProjectName`, `ProjectCode`, `CanPush`.

`resolveWorkDirs`:

- `WorkspaceID != ""` → `cwd = <base>/workspaces/<WorkspaceID>`,
  `roots =` подкаталоги проектов;
- иначе — старая логика юнитов.

CLI:

```text
opencode run --format json --auto --model unitman/main --dir /workspace ...
```

При `WorkspaceReadOnly=true` в промпт добавляется запрет на `git commit` и
`git push`.

## 7. Контракт E: OpenCode application

### 7.1 Команды

```php
final class SozdatWorkspace
{
    /** @param list<string> $projectIds */
    public function __construct(
        public readonly string $name,
        public readonly string $branch,
        public readonly array $projectIds,
        public readonly string $prompt,
        public readonly string $credentialId,
    ) {}
}
```

```php
final class PoluchitSpisokWorkspace
{
    public function __construct(
        public readonly bool $onlyActive = false,
        public readonly ?string $search = null,
    ) {}
}
```

```php
final class PoluchitSostoyanieWorkspace
{
    public function __construct(public readonly string $workspaceId) {}
}
```

```php
final class ArhivirovatWorkspace
{
    public function __construct(
        public readonly string $workspaceId,
        public readonly bool $force = false,
    ) {}
}
```

```php
final class VosstanovitWorkspace
{
    public function __construct(public readonly string $workspaceId) {}
}
```

```php
final class UdalitWorkspace
{
    public function __construct(public readonly string $workspaceId) {}
}
```

### 7.2 Порты

`UmeetUpravlyatProektamiWorkspace`:

```php
/** @param list<string> $projectIds @return list<ProektWorkspace> */
public function poluchitProekti(array $projectIds, string $accountId): array;
```

`UmeetUpravlyatWorkspace`:

```php
public function zapustit(SessiyaOpenCode $session): void;
public function ostanovit(SessiyaOpenCode $session): void;
public function vosstanovit(SessiyaOpenCode $session): void;
public function udalit(SessiyaOpenCode $session): void;
public function poluchitIzmeneniya(SessiyaOpenCode $session): RezultatProverkiIzmeneniyWorkspace;
public function poluchitRezultatOperacii(SessiyaOpenCode $session): ?string;
```

`RezultatProverkiIzmeneniyWorkspace`:

```php
final class RezultatProverkiIzmeneniyWorkspace
{
    /** @param list<array{projectId: string, name: string, dirty: bool, files: list<string>}> $projects */
    public function __construct(
        public readonly bool $success,
        public readonly array $projects,
    ) {}
}
```

### 7.3 Read-модели

`WorkspaceReadModel`:

```php
public readonly string $id;
public readonly string $name;
public readonly string $branch;
public readonly string $status;
public readonly bool $readOnly;
public readonly bool $archived;
public readonly ?string $archiveReason;
public readonly ?string $errorKey;
public readonly array $projects; // list<ProektWorkspaceReadModel>
public readonly ?string $ideLink;
public readonly int $createdAt;
public readonly int $updatedAt;
```

`SostoyanieWorkspaceReadModel`:

```php
public readonly string $id;
public readonly string $name;
public readonly string $branch;
public readonly string $status;
public readonly bool $readOnly;
public readonly bool $archived;
public readonly ?string $archiveReason;
public readonly ?string $errorKey;
public readonly array $projects;
public readonly ?string $ideLink;
public readonly bool $canAcceptMessages;
public readonly ?string $lastTurnId;
public readonly ?string $lastTurnStatus;
```

`RezultatArhivaciiWorkspace`:

```php
public readonly bool $requiresConfirmation;
public readonly array $dirtyProjects; // list<array{projectId,name,files}>
public readonly string $status;
```

### 7.4 Use cases

```php
SozdatWorkspaceUseCase::handle(SozdatWorkspace $command): string;
PoluchitSpisokWorkspaceQuery::handle(PoluchitSpisokWorkspace $query): array;
PoluchitSostoyanieWorkspaceQuery::handle(PoluchitSostoyanieWorkspace $query): SostoyanieWorkspaceReadModel;
ArhivirovatWorkspaceUseCase::handle(ArhivirovatWorkspace $command): RezultatArhivaciiWorkspace;
VosstanovitWorkspaceUseCase::handle(VosstanovitWorkspace $command): void;
UdalitWorkspaceUseCase::handle(UdalitWorkspace $command): void;
```

### 7.5 HTTP API

Все маршруты под `#[Route('/api/opencode/workspace')]`:

| Метод | Путь | Тело | Ответ |
|---|---|---|---|
| POST | `/sozdat` | `name, branch, projectIds[], prompt, credentialId` | `{id, status}` |
| GET | `/spisok` | query `onlyActive`, `search` | `list<WorkspaceReadModel>` |
| POST | `/sostoyanie` | `workspaceId` | `SostoyanieWorkspaceReadModel` |
| POST | `/arhivirovat` | `workspaceId, force?` | `RezultatArhivaciiWorkspace` |
| POST | `/vosstanovit` | `workspaceId` | `{status}` |
| POST | `/udalit` | `workspaceId` | `data: null` |

Промпты и ответы используют существующие маршруты:

- `POST /api/opencode/prompt` с `sessionId = workspaceId`;
- `POST /api/opencode/otvetit`;
- `POST /api/opencode/poluchitResultatHoda`;
- `POST /api/opencode/poluchitProgressHoda`;
- `POST /api/opencode/istoriyaSessii`.

`GET /api/opencode/spisokSessiy` получает query `type=unit|workspace`.
Элемент ответа получает `type`, `workspace`, `ideLink`.

### 7.6 Гейт и launcher

`PropuskHodaOpenCode`:

```php
if ($session->estWorkspace()) {
    $session->trebovatAktivnuyu();
    if ($session->getWorkspace()->getStatus() !== StatusWorkspace::RUNNING) {
        throw new \DomainException('opencode.workspace.not_running');
    }
    return [];
}
```

`ZapuskatelHodaOpenCode`:

- для workspace не захватывает юниты;
- передаёт `WorkspaceKontekst` в `UmeetVypolnyatHodOpenCode::nachatHod`;
- не перезаписывает название workspace через `podpisatPoPromptu`.

`WorkspaceKontekst`:

```php
final class WorkspaceKontekst
{
    /** @param list<ProektWorkspace> $projects */
    public function __construct(
        public readonly string $workspaceId,
        public readonly bool $readOnly,
        public readonly array $projects,
    ) {}
}
```

### 7.7 Уведомления

`UvedomlenieONovomSoobsheniiOpenCode` получает `sessionType: string`.

Payload:

```json
{
  "eventType": "new_message",
  "id": "...",
  "sessionId": "...",
  "sessionType": "workspace",
  "turnId": "...",
  "sessionTitle": "payments-refactor",
  "status": "ANSWERED",
  "kind": "ANSWER",
  "createdAt": 1762000000
}
```

### 7.8 Background job

`ObrabotkaWorkspaceBackgroundJob`:

- имя `opencode_workspace_reconcile`;
- задержка 5 секунд;
- `getProcessNum(): 1`;
- `run(): bool` всегда `true`.

Переходы:

```text
CREATING  + result Success -> RUNNING + запуск первого промпта
CREATING  + result Fail    -> ERROR
STARTING  + result Success -> RUNNING + snovaAktivna
STARTING  + result Fail    -> ERROR
ARCHIVING + result Success -> ARCHIVED
ARCHIVING + result Fail    -> ERROR
DELETING  + result Success -> deleteWorkspace
DELETING  + result Fail    -> ERROR
RUNNING   + initialPromptStartedAt = null -> запуск первого промпта
```

Каждый переход — через `perevestiStatusWorkspace` с ожидаемыми статусами.

## 8. Ошибки

Новые ключи `opencode.workspace.*`:

```text
name_required
name_too_long
name_already_exists
branch_required
branch_too_long
projects_required
too_many_projects
project_not_found
project_conflict
prompt_required
not_found
not_running
archived
uncommitted_changes
confirmation_required
delete_in_progress
operation_failed
initial_prompt_failed
project_branch_not_found
project_clone_failed
read_only_push_forbidden
```

Ключи добавляются в `translations/messages.ru.php` и
`translations/messages.en.php`.

## 9. Порядок слияния и интеграции

1. A, B, C, D, E разрабатываются параллельно по контрактам.
2. Слияние: A → B → C → E; D не зависит от PHP и сливается отдельно.
3. После слияния A/B/C поток E запускает полный PHP-прогон.
4. После слияния D поток F запускает `go test ./...` и проверяет
   end-to-end сценарий.
5. F обновляет `docs/opencode-sessions.md`, добавляет `docs/workspaces.md`
   и выполняет `composer all-checks`, `composer openapi-dump`,
   `php bin/console app:translations:audit`, `rr reset`.

## 10. Запреты для субагентов

- Не менять контракты из этого файла.
- Не читать чужие модули «для понимания».
- Не добавлять новые зависимости в `composer.json` / `go.mod`.
- Не переименовывать существующие классы и маршруты.
- Не делать `git commit`, `git push` и не менять общие конфиги вне своей зоны.
- Если нужен файл вне зоны — вернуть задачу Architect с указанием, какой
  контракт неполон.
