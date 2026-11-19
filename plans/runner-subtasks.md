# Пакеты подзадач: состояние контейнеров раннера и health-push

Каждый пакет самодостаточен. Исполнитель читает только перечисленные файлы и не исследует остальной репозиторий.

Корни репозиториев:

- Go-раннер: `/home/leo/go/src/unitman-runner`
- PHP unitman: `/home/leo/www/unitman`

Общие правила:

- PHP: `declare(strict_types=1);`, `final` классы, constructor property promotion, Psalm level 1.
- Go: `gofmt`, явная обработка ошибок, без паники в фоновых циклах.
- Не менять `App\Utils\Model\Reponse\Response`.
- Не добавлять зависимости, кроме `go.etcd.io/bbolt` в Go.

---

## G1. Пакет state и bbolt-хранилище

**Зависимости:** нет.

**Файлы:**

- создать `/home/leo/go/src/unitman-runner/state/state.go`
- создать `/home/leo/go/src/unitman-runner/state/bolt_store.go`
- изменить `/home/leo/go/src/unitman-runner/go.mod`
- изменить `/home/leo/go/src/unitman-runner/go.sum`
- изменить `/home/leo/go/src/unitman-runner/.gitignore`

**Разрешено читать:** `go.mod`, `.gitignore`, `AGENTS.md`.

**Контракт `state`:**

```go
package state

type ExpectedState string

const (
    ExpectedRunning ExpectedState = "running"
    ExpectedAbsent  ExpectedState = "absent"
)

type ActualState string

const (
    ActualRunning ActualState = "running"
    ActualStopped ActualState = "stopped"
    ActualAbsent  ActualState = "absent"
    ActualUnknown ActualState = "unknown"
)

type UnitState struct {
    UnitID             string        `json:"unitId"`
    ProjectID          string        `json:"projectId"`
    UnitName           string        `json:"unitName"`
    ProjectName        string        `json:"projectName"`
    ContainerName      string        `json:"containerName"`
    Expected           ExpectedState `json:"expectedState"`
    ExpectedUpdatedAt  int64         `json:"expectedUpdatedAt"`
    Actual             ActualState   `json:"actualState"`
    ActualCheckedAt    int64         `json:"actualCheckedAt"`
    MismatchSince      int64         `json:"mismatchSince"`
    LastNotifiedActual ActualState   `json:"lastNotifiedActual"`
    LastNotifiedAt     int64         `json:"lastNotifiedAt"`
}

type Store interface {
    Get(unitID string) (UnitState, bool, error)
    List() ([]UnitState, error)
    Upsert(UnitState) error
    Delete(unitID string) error
    SetActual(unitID string, actual ActualState, checkedAt int64) error
    MarkNotified(unitID string, actual ActualState, at int64) error
    Close() error
}

func Open(path string) (Store, error)
func Init(path string) error
func Default() Store
func RecordActivityResult(activity, projectID, unitID, unitName, projectName string, success bool)
func Delete(unitID string)
func SetActual(unitID string, actual ActualState, checkedAt int64)
func MarkNotified(unitID string, actual ActualState, at int64)
```

**Поведение:**

- `Open` открывает bbolt с timeout 5 секунд, создаёт bucket `units` и `meta`.
- `Default()` возвращает no-op store, если `Init` не вызывался: активности и тесты не должны падать.
- `RecordActivityResult`:
  - при `success == false` ничего не делает;
  - `activity` в `{"podgotovka","zapusk","obnovlenie","deistvie"}` ставит `ExpectedRunning`;
  - `activity == "sbros_podgotovki"` ставит `ExpectedAbsent`;
  - `activity == "udalenie"` удаляет запись;
  - `ContainerName = unitName + "." + projectName`.
- `SetActual` обновляет только `Actual` и `ActualCheckedAt`, запись не создаёт.
- `MarkNotified` обновляет `LastNotifiedActual` и `LastNotifiedAt`.
- Значения хранятся как JSON в bucket `units`, ключ — `UnitID`.

**Критерии:**

- `go build ./state/...` проходит.
- `go vet ./state/...` проходит.
- В `.gitignore` добавлен `runner-state.db`.

---

## G2. Пакет monitor: health, фактическое состояние, reconciler, cleanup

**Зависимости:** контракт G1. Пакет `state` может ещё не существовать; писать код против контракта G1.

**Файлы:**

- создать `/home/leo/go/src/unitman-runner/monitor/client.go`
- создать `/home/leo/go/src/unitman-runner/monitor/actual_state.go`
- создать `/home/leo/go/src/unitman-runner/monitor/reconciler.go`
- создать `/home/leo/go/src/unitman-runner/monitor/bootstrap.go`
- создать `/home/leo/go/src/unitman-runner/monitor/health.go`
- создать `/home/leo/go/src/unitman-runner/monitor/cleanup.go`

**Разрешено читать:** `temporal/utils/cmd.go`, `temporal/activity/unit/UnitState.go` только для `ReadEnvFile`, `config/Configuration.go`, `go.mod`, `AGENTS.md`.

**Контракт клиента:**

```go
type Client struct {
    BaseURL    string
    Secret     string
    RunnerID   string
    TaskQueue  string
    HTTPClient *http.Client
}

type HealthPayload struct {
    RunnerID    string `json:"runnerId"`
    TaskQueue   string `json:"taskQueue"`
    Active      bool   `json:"active"`
    DockerStats string `json:"dockerStats"`
    MemInfo     string `json:"memInfo"`
    Unixtime    int64  `json:"unixtime"`
}

type Notification struct {
    UnitID        string `json:"unitId"`
    ProjectID     string `json:"projectId"`
    UnitName      string `json:"unitName"`
    ProjectName   string `json:"projectName"`
    ExpectedState string `json:"expectedState"`
    ActualState   string `json:"actualState"`
    EventType     string `json:"eventType"`
    DetectedAt    int64  `json:"detectedAt"`
}

type NotificationsPayload struct {
    RunnerID      string         `json:"runnerId"`
    Notifications []Notification `json:"notifications"`
}

func (c *Client) SendHealth(ctx context.Context, payload HealthPayload) error
func (c *Client) SendNotifications(ctx context.Context, payload NotificationsPayload) error
```

**Контракт циклов:**

```go
func RunHealth(ctx context.Context, store state.Store, client *Client, workdir string)
func RunReconciler(ctx context.Context, store state.Store, client *Client, workdir string)
func RunCleanup(ctx context.Context, workdir string)
func Bootstrap(ctx context.Context, store state.Store, workdir string)
```

**Поведение:**

- HTTP: `POST` на `BaseURL + path`, заголовки `Content-Type: application/json` и `X-Runner-Signature`.
- Подпись: `hex(HMAC-SHA256(rawBody, Secret))`. Если `Secret == ""`, заголовок не отправляется.
- Таймаут клиента — 10 секунд. Успех — HTTP 200.
- `SendHealth`: путь `/api/runner/health`.
- `SendNotifications`: путь `/api/runner/unitStateNotifications`.
- `RunHealth`:
  - первый вызов сразу, затем тикер 1 минута;
  - `MemInfo` — содержимое `/proc/meminfo`;
  - `DockerStats` — `docker stats --no-stream --format json` через `utils.ExecCommand`;
  - ошибка docker не отменяет отправку: `Active: true`, stats пустые, ошибка в лог;
  - если размер stats больше 2 МиБ — отправить пустые stats и залогировать.
- `RunReconciler`:
  - вызвать `Bootstrap`, затем тикер 10 минут;
  - фактическое состояние — одной командой `docker ps -a --format '{{json .}}'`;
  - разобрать JSON-строки, построить карту `Names -> State`, убрать ведущий `/`;
  - `running` — запущен, иное — `stopped`, отсутствует — `absent`;
  - ошибка команды — `unknown`, уведомления в этом цикле не отправлять;
  - `expected running` и `actual != running` — mismatch;
  - `expected absent` и `actual != absent` — mismatch;
  - при переходе в mismatch выставить `MismatchSince`;
  - уведомлять при смене actual или первом mismatch, напоминать не чаще 30 минут, при восстановлении отправить `resolved`;
  - после HTTP 200 вызвать `MarkNotified`.
- `Bootstrap`:
  - обойти `<workdir>/projects/*/units/*`;
  - для каталогов с `compose.yaml` и `.env` прочитать `UNITMAN_UNIT_ID`, `UNITMAN_UNIT_NAME`, `UNITMAN_PROJECT_NAME`;
  - если записи нет и actual `running` или `absent` — создать запись с expected = actual;
  - при `stopped` или `unknown` запись не создавать.
- `RunCleanup`:
  - тикер 2 часа;
  - выполнить `docker image prune -af`, затем `docker builder prune -af`;
  - ошибки логировать, цикл продолжать, `--volumes` не использовать.

**Критерии:**

- `go build ./monitor/...` проходит после появления `state`.
- Нет паник при ошибках docker/HTTP.

---

## G3. Подключение monitor, удаление старых Go-активностей

**Зависимости:** контракты G1 и G2.

**Файлы:**

- изменить `/home/leo/go/src/unitman-runner/temporal/main.go`
- удалить `/home/leo/go/src/unitman-runner/temporal/activity/runner/RunnerHealthCheckActivity.go`
- удалить `/home/leo/go/src/unitman-runner/temporal/activity/runner/OchistkaDockeraActivity.go`
- создать `/home/leo/go/src/unitman-runner/docs/runner-state.md`

**Разрешено читать:** `temporal/main.go`, `config/parser.go`, `temporal/utils/temporal.go`, `AGENTS.md`, `docs/unit-state-persistence.md`.

**Контракт:**

- `const TaskQueue = "unitman-runner-queue"` использовать и для worker, и для health payload.
- `RUNNER_STATE_DB` — путь к bbolt, default `runner-state.db`.
- `RUNNER_ID` — default `os.Hostname()`.
- `RUNNER_WORK_DIR` — default `os.Getwd()`.
- `RUNNER_CALLBACK_SECRET` — из env.
- После `config.ParseConfig()`:
  - `state.Init(dbPath)`;
  - `client := &monitor.Client{BaseURL: backendURL, Secret: secret, RunnerID: runnerID, TaskQueue: TaskQueue, HTTPClient: ...}`;
  - `ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)`;
  - `go monitor.RunHealth(ctx, store, client, workdir)`;
  - `go monitor.RunReconciler(ctx, store, client, workdir)`;
  - `go monitor.RunCleanup(ctx, workdir)`;
  - `err = w.Run(worker.InterruptCh())`.
- Удалить `w.RegisterActivity(runner.RunnerHealthCheckActivity)` и `w.RegisterActivity(runner.OchistkaDockeraActivity)`.
- Удалить неиспользуемый импорт пакета `runner`, если он больше не нужен.
- `docs/runner-state.md`: назначение БД, env-переменные, расписание 1 мин / 10 мин / 2 ч, требование постоянного диска.

**Критерии:**

- `go build ./...` проходит.
- Старые активности не зарегистрированы.
- В docs описаны все env и persist-требования.

---

## G4. Хуки состояния в unit-активностях

**Зависимости:** контракт G1.

**Файлы:**

- изменить `/home/leo/go/src/unitman-runner/temporal/activity/unit/NachatPodgotovkuUnitaActivity.go`
- изменить `/home/leo/go/src/unitman-runner/temporal/activity/unit/NachatZapuskUnitaActivity.go`
- изменить `/home/leo/go/src/unitman-runner/temporal/activity/unit/NachatObnovlenieUnitaActivity.go`
- изменить `/home/leo/go/src/unitman-runner/temporal/activity/unit/NachatSbrosPodgotovkiUnitaActivity.go`
- изменить `/home/leo/go/src/unitman-runner/temporal/activity/unit/NachatUdalenieUnitaActivity.go`
- изменить `/home/leo/go/src/unitman-runner/temporal/activity/unit/NachatDeistvieUnitaActivity.go`
- изменить `/home/leo/go/src/unitman-runner/temporal/activity/unit/ProveritKonteinerUnitaActivity.go`

**Разрешено читать:** только эти файлы и `state/state.go`.

**Контракт:**

- Именованные возвраты: `(result *ResultatXxx, err error)`.
- В начале активности:
  - `defer state.RecordActivityResult("<activity>", command.ProjectId, command.Id, command.Name, command.ProjectName, result.Success == 1)`
- Имена activity:
  - `podgotovka`, `zapusk`, `obnovlenie`, `sbros_podgotovki`, `udalenie`, `deistvie`, `proverka_konteinera`.
- Для `NachatUdalenieUnitaActivity` при успехе дополнительно вызвать `state.Delete(command.Id)`.
- Для `ProveritKonteinerUnitaActivity`:
  - при `Success == 1` вызвать `state.SetActual(command.Id, state.ActualRunning, time.Now().Unix())`;
  - при `Success == 0` вызвать `state.SetActual(command.Id, state.ActualAbsent, time.Now().Unix())`.
- Не менять существующую бизнес-логику и возвращаемые значения.

**Критерии:**

- `go build ./...` проходит.
- При ошибке активности expected не меняется.

---

## G5. Go-тесты

**Зависимости:** G1, G2, G3, G4.

**Файлы:**

- создать `/home/leo/go/src/unitman-runner/state/bolt_store_test.go`
- создать `/home/leo/go/src/unitman-runner/monitor/reconciler_test.go`
- создать `/home/leo/go/src/unitman-runner/monitor/client_test.go`
- создать `/home/leo/go/src/unitman-runner/monitor/cleanup_test.go`
- создать `/home/leo/go/src/unitman-runner/monitor/health_test.go`

**Разрешено читать:** только тестируемые файлы.

**Покрыть:**

- bbolt: upsert/get/list/delete, сохранение после переоткрытия.
- reconciler: `running -> absent` даёт mismatch; повтор без изменений не спамит; восстановление даёт resolved; `unknown` не уведомляет.
- client: httptest проверяет путь, JSON и HMAC.
- cleanup: вызывает ровно `docker image prune -af` и `docker builder prune -af`, без `--volumes`.
- health: payload содержит runnerId, taskQueue, active, unixtime.

**Критерии:**

- `go test ./...` проходит.
- `golangci-lint run` проходит или фиксируются только существовавшие ранее замечания.

---

## P1. PHP Runner: модель, репозиторий, подпись

**Зависимости:** нет.

**Файлы:**

- изменить `/home/leo/www/unitman/src/Runner/Business/Model/RunnerState.php`
- изменить `/home/leo/www/unitman/src/Runner/Business/Port/RunnerRepository.php`
- изменить `/home/leo/www/unitman/src/Runner/Infra/Repository/SqlRunnerStateRepository.php`
- изменить `/home/leo/www/unitman/src/Runner/Infra/Service/RunnerCallbackSignatureChecker.php`

**Разрешено читать:** только эти файлы и `src/Runner/Tests/UseCase/Runner/SozdatDefoltniiRunnerTest.php`.

**Контракт:**

```php
// RunnerState
public function __construct(
    private string $id,
    private string $taskQueue,
    private bool $active,
    private array $dockerStats = [],
    private MemoryInfo $memoryInfo = new MemoryInfo(0, 0),
    private int $unixtimeLastHealth = 0,
) {}

public static function sozdatRunner(string $id, string $taskQueue): self;
public function isHealthFresh(int $ttlSeconds = 180, ?int $now = null): bool;
public function getUnixtimeLastHealth(): int;
public function ustanovitResultatRabotosposobnosti(
    bool $active,
    array $dockerStats,
    MemoryInfo $memoryInfo,
    ?int $unixtimeLastHealth = null,
): void;
```

```php
// RunnerRepository
public function findById(string $id): ?RunnerState;
```

```php
// RunnerCallbackSignatureChecker
public function estPodpisTelaValidna(string $body, ?string $signature): bool;
```

**Поведение:**

- `isHealthFresh`: `unixtimeLastHealth > 0 && ($now ?? time()) - unixtimeLastHealth <= $ttlSeconds`.
- `SqlRunnerStateRepository::findById`: `SELECT *` по id, вернуть `null`, если нет.
- `SqlRunnerStateRepository::getDefaultRunnerState`: выбирать строку с максимальным `COALESCE((payload->>'unixtimeLastHealth')::bigint, 0) DESC`.
- `estPodpisTelaValidna`: если секрет пуст — `true`; иначе `hash_hmac('sha256', $body, $secret)`, сравнение `hash_equals`.

**Критерии:**

- Существующий тест `SozdatDefoltniiRunnerTest` обновить под новую сигнатуру и `isHealthFresh`.
- `php -l` изменённых файлов проходит.

---

## P2. PHP Runner: health и уведомления

**Зависимости:** контракт P1.

**Файлы:**

- создать `/home/leo/www/unitman/src/Runner/Business/Command/ZaregistrirovatHealthRunnera.php`
- создать `/home/leo/www/unitman/src/Runner/Business/Command/UvedomlenieOSostoyaniiKonteynera.php`
- создать `/home/leo/www/unitman/src/Runner/Business/Command/ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera.php`
- создать `/home/leo/www/unitman/src/Runner/Business/Port/UmeetObnovlyatSostoyanieKonteyneraUnita.php`
- создать `/home/leo/www/unitman/src/Runner/Business/Utils/RunnerHealthPayloadParser.php`
- создать `/home/leo/www/unitman/src/Runner/Business/UseCase/ZaregistrirovatHealthRunneraUseCase.php`
- создать `/home/leo/www/unitman/src/Runner/Business/UseCase/ZaregistrirovatUvedomleniyaOSostoyaniiKonteyneraUseCase.php`
- изменить `/home/leo/www/unitman/src/Runner/Acl/UnitmanAdapter.php`
- изменить `/home/leo/www/unitman/src/Runner/Infra/Controller/RunnerController.php`
- изменить `/home/leo/www/unitman/src/Runner/di.php`
- изменить `/home/leo/www/unitman/config/packages/security.yaml`

**Разрешено читать:** перечисленные файлы, `src/Runner/Business/Command/SaveStepsCommand.php`, `src/Runner/Business/UseCase/UstanovitResultatProverkiRabotosposobnostiRunneraUseCase.php`, `src/Runner/Api/RunnerApiInterface.php`.

**Контракт DTO:**

```php
final class ZaregistrirovatHealthRunnera implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $runnerId,
        public readonly string $taskQueue,
        public readonly bool $active = true,
        public readonly ?string $dockerStats = null,
        public readonly ?string $memInfo = null,
        public readonly ?int $unixtime = null,
    ) {}
}
```

```php
final class UvedomlenieOSostoyaniiKonteynera
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $projectName,
        public readonly string $expectedState,
        public readonly string $actualState,
        public readonly string $eventType,
        public readonly int $detectedAt,
    ) {}
}

final class ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera implements JsonBodySerializableInterface
{
    /** @param UvedomlenieOSostoyaniiKonteynera[] $notifications */
    public function __construct(
        public readonly string $runnerId,
        public readonly array $notifications = [],
    ) {}
}
```

**Контракт порта Runner:**

```php
interface UmeetObnovlyatSostoyanieKonteyneraUnita
{
    public function obnovit(
        string $unitId,
        bool $mismatch,
        ?string $expectedState,
        ?string $actualState,
        ?int $detectedAt,
    ): void;
}
```

**Контракт эндпоинтов:**

- `POST /api/runner/health`, тело ≤ 2 МиБ, HMAC по телу.
- `POST /api/runner/unitStateNotifications`, тело ≤ 1 МиБ, HMAC по телу.
- Оба — `PUBLIC_ACCESS` в `security.yaml`.
- Возврат — `JsonResponse(true)`.
- Ошибка подписи — `AccessDeniedHttpException('runner.invalid_signature')`.
- Ошибка размера — `BadRequestHttpException('runner.health_payload_too_large')` или `runner.unit_state_payload_too_large`.
- Пустой `runnerId` — `BadRequestHttpException('runner.health_runner_id_empty')`.

**Use case health:**

- найти раннера по `runnerId`, иначе создать `RunnerState::sozdatRunner($runnerId, $taskQueue)`;
- `ustanovitResultatRabotosposobnosti(true, $dockerStats, $memInfo, $unixtime ?? time())`;
- сохранить;
- вызвать `UmeetObnovlyatStatistikuPoUnitam::obnovitStatistikuPoUnitam($dockerStats)`.
- Парсинг `dockerStats`/`memInfo` вынести в `RunnerHealthPayloadParser`, чтобы не дублировать старый use case.

**Use case уведомлений:**

- для каждого элемента вызвать порт `UmeetObnovlyatSostoyanieKonteyneraUnita::obnovit(...)`;
- `mismatch = ($eventType === 'mismatch')`;
- неизвестный `eventType` — `BadRequestHttpException('runner.unit_state_payload_empty')` не использовать; добавить отдельную валидацию с `BadRequestHttpException('runner.unit_state_payload_empty')` только для пустого списка.

**UnitmanAdapter:**

- реализовать порт через `UnitmanApi::obnovitSostoyanieKonteyneraUnita(...)`.
- Команда будет создана в U2; писать вызов против контракта:
  ```php
  new \App\Unitman\Business\Command\Unit\ObnovitSostoyanieKonteyneraUnita(
      unitId: ..., mismatch: ..., expectedState: ..., actualState: ..., detectedAt: ...
  )
  ```

**DI:**

- привязать `UmeetObnovlyatSostoyanieKonteyneraUnita` к `UnitmanAdapter`.
- удалить регистрацию `UstanovitResultatRabotosposobnostiRunneraActivity` из `di.php`.

**Критерии:**

- Контроллер не содержит бизнес-логики.
- `php -l` и `php bin/console lint:container` проходят после появления U2.

---

## P3. PHP Runner: удаление старых артефактов и тесты

**Зависимости:** P2.

**Файлы:**

- удалить `/home/leo/www/unitman/src/Runner/Infra/Workflow/RunnerHealthCheckWorkflow.php`
- удалить `/home/leo/www/unitman/src/Runner/Infra/Workflow/OchistkaDockeraWorkflow.php`
- удалить `/home/leo/www/unitman/src/Runner/Infra/BackgroundJob/RunnerHealthCheckWorkflowJob.php`
- удалить `/home/leo/www/unitman/src/Runner/Infra/BackgroundJob/OchistkaDockerJob.php`
- удалить `/home/leo/www/unitman/src/Runner/Infra/Activity/UstanovitResultatRabotosposobnostiRunneraActivity.php`
- изменить `/home/leo/www/unitman/config/workflows.php`
- создать `/home/leo/www/unitman/src/Runner/Tests/Infra/Controller/RunnerControllerHealthTest.php`
- создать `/home/leo/www/unitman/src/Runner/Tests/Infra/Controller/RunnerControllerUnitStateTest.php`

**Разрешено читать:** перечисленные файлы, `src/Runner/Tests/Infra/Controller/RunnerControllerSaveStepsTest.php`, `src/Runner/Tests/UseCase/Runner/SozdatDefoltniiRunnerTest.php`.

**Тесты:**

- health: валидная подпись, невалидная подпись, превышение размера, обновление `runner_state`.
- unit state: mismatch выставляет поля, resolved очищает, неизвестный unitId не ломает запрос.

**Критерии:**

- В `config/workflows.php` не осталось `RunnerHealthCheckWorkflow` и `OchistkaDockeraWorkflow`.
- `phpunit` по новым тестам проходит.

---

## U1. PHP Unitman: read-модель и атомарное обновление

**Зависимости:** нет.

**Файлы:**

- изменить `/home/leo/www/unitman/src/Unitman/Business/ReadModel/Unit/SpisokUnitovReadModel.php`
- создать `/home/leo/www/unitman/src/Unitman/Business/Port/Unit/UmeetObnovlyatSostoyanieKonteyneraUnita.php`
- изменить `/home/leo/www/unitman/src/Unitman/Infra/Repository/Unit/SpisokUnitovRepository.php`

**Разрешено читать:** только эти файлы и `src/Unitman/Infra/Repository/Unit/SpisokUnitovRepository.php` в части `obnovitStatistikuContainer`.

**Контракт полей read-модели** (добавить в конец конструктора):

```php
public readonly bool $containerStateMismatch = false,
public readonly ?string $containerStateMismatchExpected = null,
public readonly ?string $containerStateMismatchActual = null,
public readonly ?int $containerStateMismatchDetectedAt = null,
```

`copyAndUpdateData` обязан сохранять эти поля.

**Контракт порта:**

```php
interface UmeetObnovlyatSostoyanieKonteyneraUnita
{
    public function obnovitSostoyanieKonteynera(
        string $unitId,
        bool $mismatch,
        ?string $expectedState,
        ?string $actualState,
        ?int $detectedAt,
    ): bool;
}
```

**SQL:**

```sql
update spisok_unitov
set payload = payload || cast(:patch as jsonb)
where id = :id
```

`patch` — JSON с ключами:

- `containerStateMismatch` (bool);
- `containerStateMismatchExpected` (string|null);
- `containerStateMismatchActual` (string|null);
- `containerStateMismatchDetectedAt` (int|null).

Метод возвращает `true`, если affected rows > 0.

**Критерии:**

- Обновление не стирает `statistikaKonteinera` и другие поля payload.
- `php -l` проходит.

---

## U2. PHP Unitman: команда, use case, notifier, API, DI

**Зависимости:** контракт U1.

**Файлы:**

- создать `/home/leo/www/unitman/src/Unitman/Business/Command/Unit/ObnovitSostoyanieKonteyneraUnita.php`
- создать `/home/leo/www/unitman/src/Unitman/Business/Port/Unit/UmeetOpoveshatObIzmeneniiUnita.php`
- создать `/home/leo/www/unitman/src/Unitman/Business/UseCase/Unit/ObnovitSostoyanieKonteyneraUnitaUseCase.php`
- создать `/home/leo/www/unitman/src/Unitman/Infra/Service/WebsocketUnitEventNotifier.php`
- изменить `/home/leo/www/unitman/src/Unitman/Api/UnitmanApi.php`
- изменить `/home/leo/www/unitman/src/Unitman/di.php`

**Разрешено читать:** перечисленные файлы, `src/Unitman/Infra/Projection/WebsocketUnitEventProjection.php`, `src/Unitman/Infra/Jobs/WebsocketUnitEventReadModelJobHandler.php`, `src/Unitman/Infra/Jobs/ZadachaDlyOcherediService.php`, `src/Unitman/Business/ReadModel/Unit/WebsocketUnitEventReadModel.php`.

**Контракт команды:**

```php
final class ObnovitSostoyanieKonteyneraUnita
{
    public function __construct(
        public readonly string $unitId,
        public readonly bool $mismatch,
        public readonly ?string $expectedState = null,
        public readonly ?string $actualState = null,
        public readonly ?int $detectedAt = null,
    ) {}
}
```

**Контракт порта:**

```php
interface UmeetOpoveshatObIzmeneniiUnita
{
    public function opoveshatObObnovlenii(string $unitId): void;
}
```

**Use case:**

- вызвать `UmeetObnovlyatSostoyanieKonteyneraUnita::obnovitSostoyanieKonteynera(...)`;
- если вернулось `true` — вызвать `UmeetOpoveshatObIzmeneniiUnita::opoveshatObObnovlenii($unitId)`.

**Notifier:**

- собрать `WebsocketUnitEventReadModel($unitId, WebsocketUnitEventReadModel::OBNOVLEN, $userId, $userEmail, $unitName)`;
- поставить задачу в `websocket_unit_event` через `ZadachaDlyOcherediService::dobavitZadachuVOchered(..., 2, 2)`;
- ошибки логировать, не пробрасывать.

**UnitmanApi:**

```php
public function obnovitSostoyanieKonteyneraUnita(ObnovitSostoyanieKonteyneraUnita $command): void
```

**DI:**

- привязать `UmeetObnovlyatSostoyanieKonteyneraUnita` к `SpisokUnitovRepository`;
- привязать `UmeetOpoveshatObIzmeneniiUnita` к `WebsocketUnitEventNotifier`.

**Критерии:**

- Push уходит только при реальном обновлении строки.
- Ошибка очереди не ломает обновление read-модели.

---

## U3. PHP Unitman: тест use case

**Зависимости:** U1, U2.

**Файлы:**

- создать `/home/leo/www/unitman/src/Unitman/Tests/UseCase/Unit/ObnovitSostoyanieKonteyneraUnitaUseCaseTest.php`

**Разрешено читать:** только U1/U2 файлы и соседние тесты Unitman.

**Покрыть:**

- mismatch обновляет поля в `spisok_unitov`;
- resolved очищает поля;
- `statistikaKonteinera` не теряется;
- notifier вызывается только при `true` от репозитория.

**Критерии:**

- Тест проходит в общем прогоне PHPUnit.

---

## D1. Переводы и документация для фронта

**Зависимости:** нет.

**Файлы:**

- изменить `/home/leo/www/unitman/translations/messages.ru.php`
- изменить `/home/leo/www/unitman/translations/messages.en.php`
- создать `/home/leo/www/unitman/docs/unit-container-state.md`

**Разрешено читать:** только `translations/messages.ru.php`, `translations/messages.en.php`, `docs/runner-progress-streaming.md`.

**Переводы под ключом `runner`:**

- `health_payload_too_large`;
- `unit_state_payload_too_large`;
- `unit_state_payload_empty`;
- `health_runner_id_empty`.

**Документация:**

- поля `containerStateMismatch`, `containerStateMismatchExpected`, `containerStateMismatchActual`, `containerStateMismatchDetectedAt`;
- событие `OBNOVLEN` в Centrifugo `spisok_unitov`;
- значения `expectedState`: `running`, `absent`;
- значения `actualState`: `running`, `stopped`, `absent`, `unknown`;
- рекомендация фронту: бейдж/баннер на карточке юнита, исчезает после `resolved`.

**Критерии:**

- Оба файла переводов содержат одинаковый набор ключей.
- Документация не содержит противоречий с контрактами выше.

---

## F1. Финальная сборка и проверки

**Зависимости:** все пакеты.

**Файлы:** не создавать новые, только исправления по результатам проверок.

**Разрешено читать:** любые файлы, необходимые для исправления ошибок.

**Действия:**

1. Go:
   - `go mod tidy`;
   - `go build ./...`;
   - `go test ./...`;
   - `golangci-lint run`.
2. PHP:
   - `php bin/console cache:clear`;
   - `php bin/console lint:container`;
   - `composer run all-checks`;
   - `composer run check-deptrac`.
3. Перегенерировать `docs/openapi.json`:
   - `php bin/console nelmio:apidoc:dump --format=json > docs/openapi.json`.
4. `rr reset` в контейнере unitman.
5. Проверить сценарии:
   - health обновляет `unixtimeLastHealth`;
   - mismatch появляется в `spisok_unitov`;
   - resolved очищает поля;
   - старые Temporal workflows не запускаются;
   - очистка не вызывает `docker system prune --volumes`.

**Критерии:**

- Все проверки зелёные.
- `docs/openapi.json` содержит новые эндпоинты.
- Временные файлы и `runner-state.db` не попали в git.
