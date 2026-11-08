 # Строгие контракты ответов OpenAPI для всех ендпоинтов

## 1. Проблема

`/api/unit/list`, `/api/unit/moi`, `/api/unit/obnovit` уже переведены на строгий контракт
(`UnitListItem`). Остальные **83 операции** (8 контроллеров + декларативный `/api/login`)
по-прежнему описывают ответ как

```php
content: new OA\JsonContent(
    oneOf: [
        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
    ]
)
```

где `SuccessEnvelope.data` — просто `{type: object}`. Для массивов, примитивов и `null`
это неверно, а `200 + ErrorEnvelope` вообще не соответствует рантайму для ендпоинтов
без `try/catch`.

## 2. Рантайм: два канала ошибок

`BaseExceptionListener` **всегда** отвечает HTTP 500:

- `DomainException` → `FailEnvelope` в теле;
- `Doctrine\DBAL\Exception` → «Reconnect»-ответ;
- остальное → `ErrorEnvelope`.

Отдельно, контроллеры, обёрнутые в `try { … } catch (\Error $e)`, возвращают
**HTTP 200 + `ErrorEnvelope`** (`Response::error()`), потому что ловят ошибку до листенера.
Исключения (`\Exception`, в т.ч. DBAL) при этом не ловятся и всё равно дают 500.

Отсюда целевой контракт каждой операции:

- `200` — строгая схема успеха: `required: [status, data]`, `status: enum [success]`, типизированный `data`;
- `401` — `Не авторизован`;
- `500` — `Ошибка обработки (FailEnvelope либо ErrorEnvelope)`;
- для операций с `try/catch(\Error)` в описании 200 дополнительно указано, что перехваченная
  ошибка тоже приходит в 200 в виде `ErrorEnvelope`.

`SuccessEnvelope` остаётся объявленной схемой (обратная совместимость), но больше не
используется контроллерами.

## 3. Схемы данных (новые компоненты в `nelmio_api_doc.yaml`)

Компоненты объявляются декларативно и подключаются строковым `ref` — модульные
контроллеры не импортируют `App\Documentation\*` (deptrac/modulite).

| Компонент | Источник | Поля |
|---|---|---|
| `ProjectListItem` | `ProjectList` | id, repoId, code, name, mainBranch, isActive, state, nastroikiHukaProekta, proxyHost, users, variables, waitResultRunner, memoryLimit |
| `ProjectHookSettings` | `NastroikiHukaProekta` | avtosozdanie, avtoobnovlenie, avtoudalenie, obnovlenieBezSbrosaPodgotovki |
| `ProjectUser` | `ProjectUsersList` | userId, role |
| `ProjectVariable` | `ProjectListVariable` | tip, code, value |
| `ProjectBranch` | `VetkaProekta` | name |
| `ProjectWebhook` | `ProjectWebhookReadModel` | id, projectId, url, isActive |
| `WebhookEvent` | `WebhookEventDlySpiska` | id, type, payload, response, unixtime, sent |
| `WebhookEventPage` | `PoluchitSobitiyaWebhookaUseCase` | list[], totalRecords |
| `ProjectRunnerJob` | `ProjectRunnerJobWithoutSteps` | id, projectId, jobType, success |
| `ProjectRunnerJobDetailed` | `ProjectRunnerJob` | id, projectId, jobType, success, steps[] |
| `RunnerJobStep` | `RunnerJobStep::toArray` | command, response, success, unixtime |
| `ProjectStatistics` | `StatistikaPoProektu` | projectId, projectName, total, active, deleted |
| `Repo` | `RepoList` | id, type, name, repoUrl, token, confirmed |
| `RepoType` | `RepoTypeList` | code, name |
| `RepoProject` | `ProektHranilisha` | value, name |
| `AccountUser` | `UserList` | id, email, isBlocked, nickname |
| `ManagedAccount` | `AccountForManaging` | id, email, isBlocked, roles, password, nickname |
| `AccountSettings` | `AccountSettingsReadModel` | nickname |
| `InitializationRecord` | `InitializationRecord` | id, prop, value, init |
| `IdHolder` | `['id' => $id]` | id |
| `EventIdHolder` | `['eventId' => $id]` | eventId |
| `UnitLinkHolder` | `['link' => $link]` | link |
| `ProxyHostCheckResult` | `['result' => $bool]` | result |
| `UnitConfig` | `ConfigUnita::toArray` | build, variables, prepare, reset_prepare, up, down, services, actions |
| `UnitConfigBuild` | `KonfigStadiiSborki::toArray` | image, commands |
| `UnitConfigService` | `KonfigServisa::toArray` | name, ports, cache |
| `UnitConfigServicePort` | `PortServisa::toArray` | type, port, startUri |
| `UnitConfigServiceCache` | `CacheServisa::toArray` | files, paths |
| `UnitConfigVariable` | `AbstractConfigVariable::toArray` | id, label, type, defaultValue, options, helper |
| `ConfigVariableHelper` | `PomoschnikSsylki::toArray` | projectId, service, port, suffix, type |
| `UnitVariable` | `PeremenyaUnita::toArray` | konfig, value |
| `UnitProcessBrief` | `ProzesUnitaBezShagov` | id, unitId, userId, type, state, jobs[] |
| `UnitProcessBriefJob` | `ZadachaProzesaUnitaBezShagovZadachi` | id, type, state |
| `RunnerState` | `RunnerState::jsonSerialize` | id, taskQueue, active, dockerStats[], memoryInfo |
| `RunnerContainerStats` | `DockerContainerStats` | containerId, containerName, cpuPercent, memoryPercent, memoryUsage, netIO |
| `RunnerMemoryInfo` | `MemoryInfo` | totalAsKb, freeAsKb |

Дедупликация: `UnitConfig.actions` переиспользует существующий `UnitAction`
(`KonfigDeistviya::toArray` совпадает по форме). Для переменных оставлены **две** схемы:
`UnitConfig.variables` → `UnitConfigVariable`, а `UnitAction.variables` → `UnitActionVariable`.
Они совпадают по форме, но описывают разные места конфигурации; удалять `UnitActionVariable`
пришлось бы вместе с правкой `UnitAction`, и это ничего не даёт, кроме риска.

## 4. Матрица операций

### 4.1 `UnitConroller` (`/api/unit`)

| path | 200 `data` | error-канал |
|---|---|---|
| `sozdat` | `null` | 200 + ErrorEnvelope |
| `list` *(готово)* | `array<UnitListItem>` | 500 |
| `naitiDubliUnita` | `array<UnitListItem>` | 500 |
| `obnovit` *(готово)* | `UnitListItem` | 500 |
| `moi` *(готово)* | `array<UnitListItem>` | 500 |
| `sobrat`, `obnovitKodUnita`, `zapolnitPeremenie`, `podgotovit`, `sbrositPodgotovku`, `zapustit`, `ostanovit`, `udalit`, `udalitSlomaniyUnit`, `vipolnitDeistviye`, `proveritKonteinerUnita`, `rebuildSpisokUnitov`, `pometitKakSlomanii` | `null` | 200 + ErrorEnvelope |
| `poluchitKonfigUnita` | `oneOf[UnitConfig, array(maxItems 0)]` | 200 + ErrorEnvelope |
| `poluchitPeremenieUnita` | `array<UnitVariable>` | 200 + ErrorEnvelope |
| `poluchitSsylkuPoPomoschniku` | `UnitLinkHolder` | 200 + ErrorEnvelope |
| `poluchitVipolnenieZadachiRunnera` | `array<UnitProcessBrief>` | 200 + ErrorEnvelope |
| `poluchitShagiZadachiRunnera` | `array<RunnerJobStep>` | 200 + ErrorEnvelope |
| `poluchitKonfigIzHranilisha` | `array<UnitVariable>` | 200 + ErrorEnvelope |

### 4.2 `ProjectController` (`/api/project`)

| path | 200 `data` |
|---|---|
| `list`, `activeList`, `my`, `user/{id}` | `array<ProjectListItem>` |
| `read/{id}` | `ProjectListItem` |
| `usersList` | `array<ProjectUser>` |
| `spisokPeremenihProekta` | `array<ProjectVariable>` |
| `branchesList` | `array<ProjectBranch>` |
| `statistikaPoProektam` | `array<ProjectStatistics>` |
| `poluchitVipolnenieZadachiRunnera` | `array<ProjectRunnerJob>` |
| `poluchitShagiZadachiRunnera` | `ProjectRunnerJobDetailed` |
| `proverkaProxyHost` | `ProxyHostCheckResult` |
| `{id}/hook` | `EventIdHolder` (был inline — добавляем `required`) |
| `add`, `updateData`, `remove`, `forceRemove`, `enable`, `disable`, `addUser`, `removeUser`, `dobavitPeremenuyu`, `udalitPeremenuyu`, `izmenitPeremenuyu`, `build`, `obnovitNastroikiHuka` | `null` |

### 4.3 `ProjectWebhookController` (`/api/project/webhook`)

| path | 200 `data` |
|---|---|
| `dobavit` | `IdHolder` |
| `{projectId}/spisok` | `array<ProjectWebhook>` |
| `{id}/read` | `ProjectWebhook` |
| `poluchitSobitiyaWebhooka` | `WebhookEventPage` |
| `{id}/otkluchit`, `{id}/vkluchit`, `{id}/udalit`, `obnovit` | `null` |

### 4.4 `RepoController` (`/api/repo`)

| path | 200 `data` |
|---|---|
| `list`, `activeList` | `array<Repo>` |
| `projectList` | `array<RepoProject>` |
| `type/list` | `array<RepoType>` |
| `add`, `delete`, `changeCredentials`, `confirm` | `null` |

### 4.5 `AccountController` (`/api/account`)

| path | 200 `data` |
|---|---|
| `register` | `IdHolder` |
| `poluchitVsehPolzovatelei` | `array<AccountUser>` |
| `poluchitVsehPolzovateleiDlyAdministrorovaniya` | `array<ManagedAccount>` |
| `poluchitNastroikiPolzovatelya` | `AccountSettings` |
| `changePasswordByAdmin`, `changeMyPassword`, `changeNicknameByAdmin`, `changeRoleByAdmin`, `changeMyNickname`, `changeEmailByAdmin`, `blockByAdmin`, `unblockByAdmin`, `changeMyLocale` | `null` |

### 4.6 `InitializationController` (`/api/initialization`)

| path | 200 `data` |
|---|---|
| `proxy_host` | `null` |
| `list` | `array<InitializationRecord>` |

### 4.7 `RunnerController` (`/api/runner`)

| path | правка |
|---|---|
| `getDefaultRunnerActive` | добавить `required: ['active']` |
| `test` | добавить content-схему `RunnerState` (сейчас её нет) |
| `saveSteps` | уже строгая (`boolean`), не меняем |

Все операции `RunnerController` возвращают «сырой» `JsonResponse` без конверта
`{status, data}` — контракт описывает их собственные тела.

## 5. Верификация

```bash
docker compose exec -T web sh -lc "php bin/console cache:clear"
docker compose exec -T web sh -lc "composer openapi-dump"
docker compose exec -T web sh -lc "composer check-openapi"
docker compose exec -T web sh -lc "composer check-psalm"
docker compose exec -T web sh -lc "composer check-unitman-deptrac"
docker compose exec -T web sh -lc "composer check-account-deptrac"
docker compose exec -T web sh -lc "composer check-initialization-deptrac"
docker compose exec -T web sh -lc "composer check-runner-deptrac"
docker compose exec -T web sh -lc "rr reset"
```

## 6. Риски

- `try/catch(\Error)`-операции документируют 500, хотя чаще отвечают 200 + `ErrorEnvelope` —
  это осознанно: 500 всё ещё достижим для не-`Error` исключений (DBAL, доменные).
- `poluchitKonfigUnita` возвращает `[]` вместо объекта, когда конфигурация не задана —
  описано через `oneOf[UnitConfig, array(maxItems 0)]`.
- Часть репозиториев отдаёт «сырые» строки БД (`InitializationRecord`), поэтому у схемы
  возможны дополнительные ключи; `required` покрывает только гарантированные поля.

## 7. Результат (применено)

| Контроллер | операций | из них `data: null` | новых схем |
|---|---|---|---|
| `UnitConroller` | 24 | 14 | `UnitConfig*`, `UnitVariable`, `UnitProcessBrief*`, 9 ранних |
| `ProjectController` | 25 | 13 | `ProjectListItem`, `ProjectUser`, `ProjectVariable`, `ProjectBranch`, `ProjectRunnerJob*`, `ProjectStatistics`, `ProxyHostCheckResult`, `EventIdHolder` |
| `ProjectWebhookController` | 8 | 4 | `ProjectWebhook`, `WebhookEvent`, `WebhookEventPage`, `IdHolder` |
| `RepoController` | 8 | 4 | `Repo`, `RepoProject`, `RepoType` |
| `AccountController` | 13 | 9 | `AccountUser`, `ManagedAccount`, `AccountSettings` |
| `InitializationController` | 2 | 1 | `InitializationRecord` |
| `RunnerController` | 3 | — | `RunnerState`, `RunnerContainerStats`, `RunnerMemoryInfo` |

Итог по спецификации: **85 операций, 121 схема, 0 висячих `$ref`, 0 оставшихся
`oneOf: [SuccessEnvelope, ErrorEnvelope]`**.

Осознанные исключения (у операции нет ответа `500`):

- `InitializationController` — оба обработчика ловят `\Throwable`, слушатель исключений
  недостижим;
- `/api/login` — `lexik/jwt` отвечает сам, `500` не наш канал;
- `/api/runner/saveSteps` — публичный эндпоинт раннера, тело `boolean`, без конверта;
- `/api/project/{id}/hook` — публичный webhook; `500` добавлен, `401` отсутствует намеренно.

Проверки пройдены: `check-openapi` (4 теста), `check-psalm` (No errors), `check-deptrac`
и 4 пер-модульных deptrac (0 errors), `rr reset` выполнен.
