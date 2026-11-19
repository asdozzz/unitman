# Контракты: уведомления о новых сообщениях OpenCode

Документ является единственным источником истины для параллельной реализации.
Субагенты обязаны придерживаться контрактов и не изменять файлы вне своей зоны
ответственности.

## 1. Цель

Когда ход OpenCode переходит из `RUNNING` в терминальный статус
(`ANSWERED`, `AWAITING_INPUT`, `FAILED`), пользователь получает уведомление,
если он не находится в интерфейсе этой сессии.

Уведомление должно:

- доставляться через Centrifugo для открытых клиентов;
- сохраняться на сервере, чтобы не теряться при закрытом приложении;
- подхватываться при следующем старте приложения и после reconnect;
- содержать ссылку на сессию с `target="_blank"`;
- не дублироваться между вкладками;
- не показываться, если пользователь уже открыл эту сессию.

## 2. Границы проектов

| Проект | Путь | Что можно менять |
|---|---|---|
| Backend | `www/unitman` | Только файлы, перечисленные в зоне ответственности задачи |
| Frontend | `www/unitman-front` | Только файлы, перечисленные в зоне ответственности задачи |
| Quasar-фронтенд | `www/unitman-quasar` | **Запрещено менять** |
| Go runner | `unitman-runner` | **Не требуется менять** |

Запрещено:

- читать или индексировать `vendor/`, `var/`, `node_modules/`, `.yarn/`, `dist/`;
- редактировать автогенерируемые файлы (`*.pb.go`, `openapi/generated`, `unitman.d.ts`) вручную;
- выполнять `git commit`, `git push`, `git reset`;
- менять смежные модули сверх контракта.

## 3. Схема БД

Новая миграция в `src/OpenCode/Migrations/`.

```sql
create table opencode_notification (
    id            varchar(64)  not null primary key,
    account_id    varchar(128) not null,
    session_id    varchar(64)  not null,
    turn_id       varchar(64)  not null,
    session_title varchar(256) not null default '',
    status        varchar(32)  not null,
    kind          varchar(32)  null,
    created_at    integer      not null,
    read_at       integer      null
);

create unique index opencode_notification_turn_uq
    on opencode_notification (turn_id);

create index opencode_notification_account_unread_idx
    on opencode_notification (account_id, read_at);
```

Правила:

- `turn_id` уникален: повторная вставка того же хода не создаёт дубль.
- `read_at is null` — уведомление непрочитанное.
- `kind` равен `ANSWER`, `QUESTION`, `ERROR` или `null`.

## 4. Контракт уведомления

Полезная нагрузка и элемент списка имеют одну форму.

```json
{
  "eventType": "new_message",
  "id": "uuid",
  "sessionId": "uuid",
  "turnId": "uuid",
  "sessionTitle": "Название сессии",
  "status": "ANSWERED",
  "kind": "ANSWER",
  "createdAt": 1730000000
}
```

Поля:

| Поле | Тип | Обязательность | Смысл |
|---|---|---|---|
| `eventType` | `"new_message"` | да | Тип события |
| `id` | string | да | Идентификатор уведомления |
| `sessionId` | string | да | Идентификатор сессии OpenCode |
| `turnId` | string | да | Идентификатор хода |
| `sessionTitle` | string | да | Заголовок сессии, может быть пустым |
| `status` | string | да | `ANSWERED`, `AWAITING_INPUT`, `FAILED` |
| `kind` | string/null | да | `ANSWER`, `QUESTION`, `ERROR`, `null` |
| `createdAt` | integer | да | Unix-время создания |

## 5. Centrifugo

- Канал: `opencode_notifications#<hex>`.
- `<hex>` = `hash_hmac('sha256', 'opencode.notifications:' . $accountId, $secret)`.
- `$secret` берётся из `%env(CENTRIFUGO_SECRET)%`.
- Имя канала вычисляет только backend.
- Frontend получает имя канала через API и не строит его самостоятельно.
- Payload публикации — объект из раздела 4.
- Ошибка публикации логируется и не влияет на завершение хода.
- Непрочитанные уведомления остаются в БД и будут отданы через API.

## 6. HTTP API

### 6.1 Получить непрочитанные уведомления и канал

`GET /api/opencode/uvedomleniya`

Query-параметры:

| Параметр | Тип | Обязательность | По умолчанию |
|---|---|---|---|
| `limit` | integer | нет | `50` |

Ответ:

```json
{
  "status": "success",
  "data": {
    "channel": "opencode_notifications#<hex>",
    "notifications": [
      {
        "eventType": "new_message",
        "id": "uuid",
        "sessionId": "uuid",
        "turnId": "uuid",
        "sessionTitle": "Название сессии",
        "status": "ANSWERED",
        "kind": "ANSWER",
        "createdAt": 1730000000
      }
    ]
  }
}
```

Ошибки:

- `401` — нет или истёк JWT;
- `500` — стандартный конверт ошибки.

### 6.2 Захватить и пометить уведомления прочитанными

`POST /api/opencode/otmetitUvedomleniyaProchitannimi`

Запрос:

```json
{
  "ids": ["uuid-1", "uuid-2"]
}
```

Ограничения:

- `ids` обязателен и непуст;
- не более `100` идентификаторов за запрос;
- дубликаты в `ids` игнорируются.

Ответ:

```json
{
  "status": "success",
  "data": {
    "ids": ["uuid-1"]
  }
}
```

`ids` в ответе — только те уведомления, которые **этот** запрос перевёл из
непрочитанных в прочитанные. Уже прочитанные и чужие идентификаторы
отсутствуют.

Семантика захвата:

- выполняется атомарно на уровне БД;
- только одна вкладка или устройство получает каждый id;
- frontend показывает popup только для полученных id.

## 7. PHP-контракты backend

### 7.1 Read-model

`App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode`

Поля:

```php
public readonly string $id;
public readonly string $accountId; // внутреннее поле, в toArray() не попадает
public readonly string $sessionId;
public readonly string $turnId;
public readonly string $sessionTitle;
public readonly string $status;
public readonly ?string $kind;
public readonly int $createdAt;
```

`accountId` нужен только инфраструктуре для заполнения `account_id` и выбора
персонального канала. В публичный payload он не попадает.

Метод `toArray(): array` возвращает форму из раздела 4, включая
`eventType: 'new_message'`, и **не включает** `accountId`.

### 7.2 Репозиторий

`App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository`

```php
public function save(UvedomlenieONovomSoobsheniiOpenCode $notification): bool;

/**
 * @return list<UvedomlenieONovomSoobsheniiOpenCode>
 */
public function findNeprochitannie(string $accountId, int $limit): array;

/**
 * @param list<string> $ids
 * @return list<string> идентификаторы, реально переведённые в прочитанные
 */
public function zahvatitNeprochitannie(string $accountId, array $ids, int $now): array;
```

Требования:

- `save()` использует `insert ... on conflict (turn_id) do nothing`;
- `save()` возвращает `true`, только если строка вставлена;
- `zahvatitNeprochitannie()` использует `update ... set read_at = :now where account_id = :accountId and id in (:ids) and read_at is null returning id`;
- выборка и захват фильтруют по `account_id`.

### 7.3 Публикатор

`App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode`

```php
public function publish(UvedomlenieONovomSoobsheniiOpenCode $notification): void;
```

Реализация использует `Fresh\CentrifugoBundle\Service\CentrifugoInterface`.

### 7.4 Имя канала

`App\OpenCode\Business\Port\UmeetOpredelyatKanalUvedomleniyOpenCode`

```php
public function kanalDlyaAccount(string $accountId): string;
```

Единственная реализация формирует имя по правилу из раздела 5.

### 7.5 Завершение хода

`App\OpenCode\Business\Utils\ZavershenieHodaOpenCode`

```php
public function zavershit(HodOpenCode $turn): HodOpenCode;
```

Обязанности:

1. Если ход не в `RUNNING`, вернуть его без изменений.
2. Запросить результат у `UmeetVypolnyatHodOpenCode::poluchitResultatHoda()`.
3. Если результата нет — вернуть ход без изменений.
4. Применить результат через `HodOpenCode::primenitRezultat()`.
5. Обновить ход через `HodOpenCodeRepository::update()`.
6. Запомнить внешнюю сессию, если она появилась.
7. Освободить юниты, если ход стал терминальным.
8. Создать уведомление через `UvedomleniyaOpenCodeRepository::save()`.
9. Если `save()` вернул `true`, опубликовать уведомление через
   `UmeetPeredavatUvedomleniyaOpenCode::publish()`.
10. Ошибка публикации не должна отменять завершение хода.

Уведомление создаётся только при переходе из `RUNNING` в терминальный статус.
`status` и `kind` берутся из итогового состояния хода и результата.

### 7.6 Watchdog

Класс в `App\OpenCode\Infra\BackgroundJob\...`.

```php
public function getName(): string;      // 'opencode_turn_watchdog'
public function getGroup(): BackgroundJobGroup; // BackgroundJobGroup::timers
public function getDelay(): int;        // 5
public function getProcessNum(): int;   // 1
public function run(): bool;
```

Поведение:

1. Выбрать не более `10` ходов в статусе `RUNNING`.
2. Для каждого вызвать `ZavershenieHodaOpenCode::zavershit()`.
3. Ошибка одного хода логируется и не прерывает обработку остальных.
4. Всегда возвращать `true`, если инфраструктурная ошибка поймана внутри.

Для выборки добавить в `HodOpenCodeRepository`:

```php
/**
 * @return list<HodOpenCode>
 */
public function findVProzesse(int $limit): array;
```

SQL:

```sql
select * from opencode_turn
where status = 'RUNNING'
order by created_at
limit :limit
```

## 8. Контракты frontend

### 8.1 TypeScript-тип

`src/modules/opencode/model/UvedomlenieOpenCode.ts`

```ts
export type StatusUvedomleniyaOpenCode =
    | "ANSWERED"
    | "AWAITING_INPUT"
    | "FAILED";

export type VidUvedomleniyaOpenCode =
    | "ANSWER"
    | "QUESTION"
    | "ERROR";

export type UvedomlenieOpenCode = {
    eventType: "new_message";
    id: string;
    sessionId: string;
    turnId: string;
    sessionTitle: string;
    status: StatusUvedomleniyaOpenCode;
    kind: VidUvedomleniyaOpenCode | null;
    createdAt: number;
};

export function pohozheNaUvedomlenie(dannie: unknown): dannie is UvedomlenieOpenCode;
```

### 8.2 Сервис

В `src/modules/opencode/services/OpenCodeService.ts` добавить:

```ts
uvedomleniya(params?: { limit?: number }) {
    return apiClient.get('/opencode/uvedomleniya', params);
}

otmetitUvedomleniyaProchitannimi(params: { ids: string[] }) {
    return apiClient.post('/opencode/otmetitUvedomleniyaProchitannimi', params);
}
```

До регенерации OpenAPI методы типизируются локальными типами из
`UvedomlenieOpenCode.ts`. Автогенерируемый `unitman.d.ts` вручную не менять.

### 8.3 Стор

`src/modules/opencode/store/StorUvedomleniyOpenCode.ts`

```ts
type NovoeUvedomlenieHandler = (u: UvedomlenieOpenCode) => void;

state: {
    kanal: string | null;
    zagruzhaem: boolean;
    oshibka: string | null;
}

actions: {
    /** Идемпотентно: получить канал и непрочитанные, подписаться, захватить. */
    podklyuchit(): Promise<void>;

    /** Отписаться и очистить состояние. */
    otklyuchit(): void;

    /** Повторно получить непрочитанные после reconnect. */
    zagruzitNeprochitannie(): Promise<void>;

    /** Зарегистрировать обработчик показа. */
    podpisatsyaNaPokaz(handler: NovoeUvedomlenieHandler): () => void;
}
```

Правила:

- используется singleton `useCentrifugo()`;
- подписка создаётся на `channel` из ответа API;
- повторный `podklyuchit()` не создаёт вторую подписку;
- при `subscribed` повторно вызывается `zagruzitNeprochitannie()`;
- перед передачей обработчикам уведомления захватываются через
  `otmetitUvedomleniyaProchitannimi`;
- обработчикам передаются только захваченные id;
- если запрос захвата упал, уведомления передаются с локальной дедупликацией
  по `id` в пределах вкладки;
- `otklyuchit()` снимает только подписку уведомлений и не отключает общий
  Centrifugo-клиент.

### 8.4 UI-контракт

Ответственность UI-слоя:

- зарегистрировать обработчик через `podpisatsyaNaPokaz()`;
- если текущий маршрут — `opencode_chat` и `params.id === sessionId`:
  - не показывать popup;
  - если `useStorChataOpenCode().sessiyaId === sessionId`, вызвать
    `perezagruzitSessiyu()`;
- иначе показать Quasar `Notify`.

Требования к `Notify`:

```ts
Notify.create({
    html: true,
    message: '<a href="..." target="_blank" rel="noopener noreferrer">...</a>',
    caption: '...',
    icon: 'forum',
    color: 'primary',
    timeout: 0,
    actions: [{ icon: 'close', color: 'white' }],
});
```

- `href` строится через `router.resolve({ name: 'opencode_chat', params: { id: sessionId } })`;
- заголовок сессии и URL экранируются перед вставкой в HTML;
- ссылка обязательно открывается в новой вкладке;
- popup не скрывается автоматически;
- тексты берутся из i18n.

### 8.5 i18n

Ключи в `ru.json` и `en.json`:

```json
{
  "opencode": {
    "notifications": {
      "new_answer": "Новое сообщение в сессии «{title}»",
      "new_question": "OpenCode ждёт ответа в сессии «{title}»",
      "new_error": "Ошибка выполнения в сессии «{title}»",
      "open_new_tab": "Открыть в новой вкладке",
      "close": "Закрыть"
    }
  }
}
```

Английские значения обязательны.

## 9. Зоны ответственности субагентов

### Задача A. Backend: инфраструктура уведомлений

Владеет только новыми/следующими файлами:

- `src/OpenCode/Migrations/Version*.php` (новая миграция);
- `src/OpenCode/Business/ReadModel/UvedomlenieONovomSoobsheniiOpenCode.php`;
- `src/OpenCode/Business/Port/UvedomleniyaOpenCodeRepository.php`;
- `src/OpenCode/Business/Port/UmeetPeredavatUvedomleniyaOpenCode.php`;
- `src/OpenCode/Business/Port/UmeetOpredelyatKanalUvedomleniyOpenCode.php`;
- `src/OpenCode/Infra/Repository/SqlUvedomleniyaOpenCodeRepository.php`;
- `src/OpenCode/Infra/Service/CentrifugoUvedomleniyaOpenCode.php`;
- `src/OpenCode/Infra/Service/KanalUvedomleniyOpenCode.php`;
- `src/OpenCode/di.php` — только регистрация новых портов и сервисов;
- новые тесты для перечисленного.

Не трогает:

- `HodOpenCodeRepository`, `SqlHodOpenCodeRepository`;
- `PoluchitResultatHodaQuery`;
- контроллер;
- frontend.

### Задача B. Backend: завершение хода, watchdog и API

Владеет только следующими файлами:

- `src/OpenCode/Business/Utils/ZavershenieHodaOpenCode.php` (новый);
- `src/OpenCode/Business/UseCase/PoluchitResultatHodaQuery.php` (рефакторинг);
- `src/OpenCode/Business/Port/HodOpenCodeRepository.php` (добавление метода);
- `src/OpenCode/Infra/Repository/SqlHodOpenCodeRepository.php` (добавление метода);
- `src/OpenCode/Infra/BackgroundJob/ZavershenieHodovOpenCode/*` (новые);
- новые команды, query и use case для уведомлений;
- `src/OpenCode/Infra/Controller/OpenCodeController.php`;
- новые тесты для перечисленного.

Не трогает:

- инфраструктуру уведомлений из задачи A;
- `di.php`;
- frontend.

Задача B использует интерфейсы задачи A строго по контракту из разделов 7.2–7.4.

### Задача C1. Frontend: транспорт уведомлений

Владеет только следующими файлами:

- `src/modules/opencode/model/UvedomlenieOpenCode.ts`;
- `src/modules/opencode/model/websocket/PodpiskaNaUvedomleniya.ts`;
- `src/modules/opencode/store/StorUvedomleniyOpenCode.ts`;
- `src/modules/opencode/services/OpenCodeService.ts`.

Не трогает:

- `App.vue`;
- i18n;
- UI-компоненты;
- `ChatOpenCode.ts`, `PodpiskaNaProgressHoda.ts`, `centrifugeClient.ts`;
- сгенерированные типы.

### Задача C2. Frontend: UI и подключение к приложению

Владеет только следующими файлами:

- новый компонент `src/modules/opencode/view/UvedomleniyaOpenCode.vue`;
- `src/App.vue`;
- `src/translator/ru.json`;
- `src/translator/en.json`.

Не трогает:

- стор, сервис и модель из задачи C1;
- `ChatOpenCode.vue`, `StorChataOpenCode.ts`;
- `centrifugeClient.ts`;
- сгенерированные типы;
- `www/unitman-quasar`.

Задача C2 использует стор задачи C1 строго по контракту из раздела 8.3.

## 10. Порядок интеграции

1. Задачи A, B, C1, C2 выполняются параллельно.
2. После A и B запускается финальная интеграция backend:
   - `php bin/console doctrine:migrations:migrate`;
   - `composer openapi-dump`;
   - `composer check-translations`;
   - `composer check-deptrac`;
   - `composer check-opencode-deptrac`;
   - `composer all-checks`.
3. После backend-интеграции выполняется регенерация frontend-типов:
   - скопировать `docs/openapi.json` в `unitman-front/openapi/unitman.json`;
   - выполнить `yarn gen:api --offline`;
   - при необходимости заменить локальные типы в `OpenCodeService.ts` на
     сгенерированные.
4. После C1 и C2 выполняется `yarn build` в `unitman-front`.
5. Ручные сценарии:
   - пользователь на другой странице;
   - пользователь в другой сессии;
   - пользователь уже в этой сессии;
   - приложение было закрыто и открыто заново;
   - две вкладки одновременно;
   - reconnect Centrifugo;
   - ссылка открывается в новой вкладке.
