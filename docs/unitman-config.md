# Конфиг юнита `unitman.yaml`

Полное руководство по файлу `unitman.yaml` — как описать сборку и запуск тестового стенда,
какие переменные показать пользователю и какие действия ему доступны.

- [Где лежит конфиг и как он читается](#где-лежит-конфиг-и-как-он-читается)
- [Минимальный пример](#минимальный-пример)
- [Полный пример](#полный-пример)
- [Блоки конфига](#блоки-конфига)
  - [`variables` — переменные](#variables--переменные)
  - [`prepare` / `reset_prepare` / `up` / `down` — стадии жизни стенда](#prepare--reset_prepare--up--down--стадии-жизни-стенда)
  - [`services` — сервисы стенда](#services--сервисы-стенда)
  - [`actions` — действия в UI](#actions--действия-в-ui)
  - [`build` — образ сборки](#build--образ-сборки)
  - [`unitman_volumes` — тома юнита](#unitman_volumes--тома-юнита)
- [Хелпер ссылки: «направить юнит на другой юнит»](#хелпер-ссылки-направить-юнит-на-другой-юнит)
- [Занятость юнита и отложенные действия](#занятость-юнита-и-отложенные-действия)
- [Сводная таблица валидации](#сводная-таблица-валидации)
- [Коды ошибок](#коды-ошибок)

---

## Где лежит конфиг и как он читается

`unitman.yaml` лежит **в корне репозитория проекта** и читается из той ветки, под которую
создаётся юнит. Файл скачивается адаптерами Git-хостингов
([`GitlabAdapter`](../src/Unitman/Infra/Adapter/StorageApiAdapter/GitlabAdapter.php),
[`GithubAdapter`](../src/Unitman/Infra/Adapter/StorageApiAdapter/GithubAdapter.php)) и
разбирается [`YamlParser`](../src/Unitman/Infra/Adapter/YamlParser.php).

Валидация и разбор — в домене: [`ConfigUnita`](../src/Unitman/Business/Model/Unit/ConfigUnita.php)
и классы из [`ConfigUnita/`](../src/Unitman/Business/Model/Unit/ConfigUnita). Ошибки имеют
ключи вида `unit.config.*` (см. [коды ошибок](#коды-ошибок)).

---

## Минимальный пример

Обязательны только блоки `prepare`, `reset_prepare`, `up`, `down` и `services` — каждый должен
быть непустым. Блоки `variables`, `actions`, `build` и `unitman_volumes` необязательны.

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

---

## Полный пример

```yaml
# ─── Переменные, которые пользователь заполняет при создании/обновлении юнита ───
variables:
  - id: AUTH_TOKEN            # имя переменной (попадает в окружение стенда)
    label: Токен авторизации  # подпись в UI
    type: string              # string | integer | float | collection
    defaultValue: dev-token

  - id: DICTIONARY_SERVICE
    label: Сервис справочников
    type: collection
    options:                  # для type=collection — фиксированный список
      - id: http://v1.dict.local
        name: Версия 1
      - id: http://v2.dict.local
        name: Версия 2
    defaultValue: http://v1.dict.local

  # Переменная с хелпером: значение — это другой юнит, хелпер строит ссылку на него.
  - id: ORDER_SERVICE
    label: Сервис заказов
    type: string
    helper:                          # как построить URL до выбранного юнита
      projectId: services/orders
      service: web
      port: 8080
      type: http                     # http | ws | ... (протокол ссылки)
      suffix: /api                   # необязательный суффикс пути
    defaultValue: master

# ─── Стадии жизни стенда ───
# prepare — один раз при создании юнита (клонирование/инициализация)
prepare:
  - cp -f .env.runner .env
  - mkdir -p config/jwt
  - openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:my_password
  - openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:my_password

# reset_prepare — очистка перед повторной подготовкой
reset_prepare:
  - rm -rf config/jwt
  - rm -f .env

# up — поднятие контейнеров стенда
up:
  - docker compose up -d --build
  - docker compose run web composer install --optimize-autoloader
  - docker compose run web php bin/console doctrine:migrations:migrate

# down — остановка/снос стенда
down:
  - docker compose down

# ─── Сервисы стенда: порты (для proxy/ссылок) ───
services:
  web:
    ports:
      - port: 80
        type: http
        startUri: /            # страница «здоровья», открывается в UI
      - port: 8080
        type: http
      - port: 5043
        type: tcp

# ─── Пользовательские действия (кнопки в UI) ───
actions:
  - id: php-console
    name: "php bin/console"
    variables:
      - id: BIN_CONSOLE
        label: BIN_CONSOLE
        type: string
        defaultValue: "debug:router"
    commands:
      # ${UNITMAN_ACTION_<ID_ПЕРЕМЕННОЙ>} подставляет значение переменной действия
      - docker compose exec php php bin/console ${UNITMAN_ACTION_BIN_CONSOLE}
    prepare: true            # выполнять на подготовленном стенде

# ─── Необязательный блок: тома юнита (файлы из медиатеки) ───
unitman_volumes:
  - dir: db                  # уникальный ключ тома внутри конфига
    name: БД                 # подпись в UI
```

---

## Блоки конфига

### `variables` — переменные

Список переменных, которые пользователь заполняет при создании юнита (`POST /api/unit/sozdat`)
или обновляет (`POST /api/unit/zapolnitPeremenie`).

| Поле | Обязательно | Описание |
| --- | --- | --- |
| `id` | да | Имя переменной (используется как ключ значения) |
| `label` | да | Подпись в UI |
| `type` | да | `string`, `integer`, `float` или `collection` |
| `defaultValue` | нет | Значение по умолчанию |
| `options` | для `collection` | Список вариантов: `[{id, name}]` |
| `helper` | нет | Описание ссылки на другой юнит (см. [ниже](#хелпер-ссылки-направить-юнит-на-другой-юнит)) |

Типы переменных:

- `string` — произвольная строка;
- `integer` — целое число (значение валидируется при заполнении);
- `float` — число с плавающей точкой;
- `collection` — выбор из фиксированного списка `options`.

Неизвестный тип приводит к ошибке `unit.config_variable_factory.type_invalid`.

### `prepare` / `reset_prepare` / `up` / `down` — стадии жизни стенда

Каждый блок — **непустой список команд** (строк), выполняемых на стадии:

| Блок | Когда выполняется |
| --- | --- |
| `prepare` | Один раз при создании юнита — клонирование и инициализация |
| `reset_prepare` | Очистка перед повторной подготовкой |
| `up` | Поднятие контейнеров стенда |
| `down` | Остановка/снос стенда |

Пустой или отсутствующий блок — ошибка (`unit.config.prepare_is_empty`,
`unit.config.resetPrepare_is_empty`, `unit.config.up_is_empty`, `unit.config.down_is_empty`).

### `services` — сервисы стенда

Обязательный блок: карта `имя сервиса → описание`.

```yaml
services:
  web:                      # имя сервиса обязательно
    ports:
      - port: 80            # 0–65535
        type: http          # обязателен, приводится к нижнему регистру
        startUri: /         # необязательно, страница «здоровья»
```

Пустой блок — ошибка `unit.config.services_is_empty`.

Ссылка на порт сервиса строится по протоколу `type`:

| `type` | Шаблон ссылки |
| --- | --- |
| `http` (или не указан) | `{scheme}://{port}.{unit}.{project}.{publicAddress}[:{publicAddressPort}]` — через публичный адрес раннера |
| `ws`, `wss` | `{wsScheme}://{port}.{unit}.{project}.{publicAddress}[:{publicAddressPort}]` — через публичный адрес; схема зависит от адреса: `https` → `wss`, иначе `ws` |
| прочие (`tcp`, …) | `{type}://{unit}.{project}:{port}` — напрямую на юнит |

Протокол нормализуется (обрезка пробелов + нижний регистр). `startUri` дописывается в конец
ссылки для любого протокола.

Примеры при `publicAddress = https://unitman.example.ru`:

- `http` на порту `8080` → `https://8080.myunit.myproject.unitman.example.ru`;
- `ws` на порту `8080` (или `wss`) → `wss://8080.myunit.myproject.unitman.example.ru`;
- при `publicAddress = http://unitman.example.ru` тот же `ws` → `ws://8080.myunit.myproject.unitman.example.ru`;
- `tcp` на порту `5432` → `tcp://myunit.myproject:5432`.

Для `http`, `ws` и `wss` публичный адрес раннера обязателен: если он пуст или не содержит схему и хост
(`parse_url` не даёт `scheme` + `host`), построение ссылки завершается ошибкой
`unit.runner_public_address_missing`. Прямые протоколы (`tcp` и прочие) в публичном адресе не нуждаются.

Порт read-only редактора файлов юнита (`UNITMAN_IDE_PORT`, по умолчанию `47000`) зарезервирован:
указывать его в `services.*.ports` нельзя — IDE работает в network namespace юнита и сервис
на этом порту перекрыл бы редактор.

### `actions` — действия в UI

Кнопки, доступные на юните. Каждое действие имеет свой набор переменных и команды.

| Поле | Обязательно | Описание |
| --- | --- | --- |
| `id` | да | Идентификатор действия |
| `name` | да | Подпись кнопки |
| `commands` | да | Непустой массив команд |
| `variables` | нет | Переменные действия (те же типы, что и у `variables`) |
| `prepare` | нет | Выполнять на подготовленном стенде (по умолчанию `false`) |

В командах подставляются значения переменных действия через
`${UNITMAN_ACTION_<ID_ПЕРЕМЕННОЙ>}`.

### `unitman_volumes` — тома юнита

Описывает тома, которые пользователь может наполнить файлом из медиатеки.

| Поле | Обязательно | Описание |
| --- | --- | --- |
| `dir` | да | Уникальный ключ тома внутри конфига |
| `name` | да | Подпись в UI |

Дубликат `dir` — ошибка `unit.config.volumes.dir_is_not_unique`.

Тома заполняются независимо от переменных: клиент передаёт `dir` (ключ тома из конфига) и
`fileId` архива, загруженного в медиатеку как черновик (см. `POST /api/unit/sozdat`,
`POST /api/unit/zapolnitPeremenie`). Имя тома всегда берётся из конфига.

В `zapolnitPeremenie` семантика `fileId` такая: поле не передано — оставить текущий файл,
`fileId: null` — очистить файл тома. Список доступных томов клиент получает из ответа
`POST /api/unit/poluchitKonfigIzHranilisha` (схема `UnitVolume`).

---

## Хелпер ссылки: «направить юнит на другой юнит»

Ключевая возможность Unitman — направить один юнит на стенд другого проекта. Для этого у
переменной описывается блок `helper`:

```yaml
variables:
  - id: ORDER_SERVICE
    label: Сервис заказов
    type: string       # тип может быть любым: helper не зависит от типа
    defaultValue: master
    helper:
      projectId: services/orders  # проект, стенд которого нужен
      service: web                # сервис внутри стенда
      port: 8080                  # порт сервиса
      type: http                  # протокол ссылки: http (по умолчанию), ws, ...
      suffix: /api                # необязательный суффикс пути
```

Обязательные поля хелпера: `projectId`, `service`, `port`. Поле `type` нормализуется
(обрезка пробелов + нижний регистр), пустое значение даёт `http`; недопустимый протокол —
ошибка `unit.config.pomoschnik.type_invalid`.

Ссылка строится по тому же правилу, что и для сервисов юнита: для `http`, `ws` и `wss` — через
публичный адрес раннера (для `ws`/`wss` схема выбирается по адресу: `https` → `wss`, иначе `ws`),
для остальных протоколов — `{type}://{unit}.{project}:{port}`. Невалидный публичный адрес для
`http`/`ws`/`wss` даёт `unit.runner_public_address_missing`. Вместо `startUri` из конфига
подставляется `suffix` из запроса хелпера.

При создании юнита клиент передаёт вместе со значением `targetUnitId`, и Unitman сохраняет
связь «переменная → целевой юнит», а также собирает ссылку на выбранный стенд.

Список выбора юнита в хелпере показывает только те юниты, у которых уже есть подходящая
ссылка `service/port/type` в `links`. Ссылки появляются после успешной сборки
(`KonfigUnitaUstanovlen`); запуск и подготовка для этого не требуются. Юнит, который
собран, но остановлен, остаётся доступным для выбора.

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

Если хелпер из конфига убирают, связи по этой переменной удаляются при реконцилиации конфига.

---

## Занятость юнита и отложенные действия

Этот раздел описывает не файл `unitman.yaml`, а поведение юнита во время работы
над ним. Знание нужно при настройке автообновления и автоудаления: занятость
решает, применится ли вебхук сразу или будет отложен.

### Реестр занятости `unit_zanyatost`

Строка таблицы — «юнит кем-то занят»: `unit_id` (первичный ключ), непрозрачный
токен владельца `vladelec`, тип `tip`
([`TipZanyatosti`](src/Unitman/Business/Model/Unit/TipZanyatosti.php:15)),
`zahvacheno_v` и `istekaet_v`. Захват — атомарный upsert с условием
`istekaet_v < now()`, поэтому гонку выигрывает ровно один владелец, а второй
получает отказ (`rowCount() = 0`). TTL захвата — 20 минут: это больше
`WorkflowExecutionTimeout` хода OpenCode (15 минут), иначе действующий ход мог
бы потерять юнит до собственного завершения.

Типы занятости:

| `tip` | Источник | Что значит |
| --- | --- | --- |
| `opencode_hod` | [`ZahvatitZanyatostUnitovUseCase`](src/Unitman/Business/UseCase/Unit/ZahvatitZanyatostUnitovUseCase.php:23) | Юнит захвачен ходом OpenCode, владелец — `opencode:<turnId>` |
| `prozes` | вычисляется при чтении | У юнита есть незавершённый процесс; в таблице не хранится |

Модуль `Unitman` не знает про OpenCode: токен владельца — просто строка,
которую он возвращает при освобождении. Зависимость идёт только в одну
сторону — `OpenCode → Unitman`.

Единая точка ответа «можно ли трогать юнит» —
[`ProverkaSvobodyUnita`](src/Unitman/Business/Utils/ProverkaSvobodyUnita.php:24).
Она проверяет **только внешний захват**; собственные незавершённые процессы
остаются заботой агрегата, который видит их в event store и сам отвечает
`unit.wait_another_proccess`.

### Что происходит с вебхуком

| Событие | Юнит свободен | Юнит занят `opencode_hod` |
| --- | --- | --- |
| `KOD_OBNOVLEN` | метка времени обновляется, затем запускается процесс обновления | метка времени обновляется **немедленно**, запуск обновления откладывается |
| `VETKA_UDALENA` | запускается процесс удаления | удаление откладывается |
| `VETKA_SMERZHENA` | если цель слияния совпадает с `mainBranch` проекта — запускается процесс удаления, иначе возвращается `not_main_branch` | удаление откладывается |
| `VETKA_SOZDANA` | создаётся юнит | — |

Метка `unixtimePoslednegoIzmeneniya` обновляется всегда и сразу: это событие
event store, раннер в нём не участвует, занятость ему не мешает.

Несобранный юнит **не** откладывается: состояние не транзиентное, сам он не
соберётся, и намерение висело бы вечно. Обработчик по-прежнему возвращает
причину `invalid status`. То же для юнита, которому обновление не нужно
(`ne nugno obnovlyat`).

Проверка свободы выполняется в обработчике **явно**, а не через агрегат:
`handleSystem()` вызывает `dobavitProzesObnovleniya(..., neproveryatProzesi: true)`
и `dobavitProzesUdaleniya(..., neproveryatProzesi: true)`, то есть агрегат
удаление или обновление не остановит.

Результат обработки вебхука — строка. Кроме прежних значений (`ok`,
`empty ids`, `invalid`, причины ошибок) появилось `deferred`: событие принято,
намерение сохранено, применение произойдёт автоматически. Для слияния в ветку,
отличную от `mainBranch`, возвращается `not_main_branch`: событие валидно, но
юнит удалять не нужно.

### Слияние ветки юнита в mainBranch

Основной сигнал — события систем контроля версий:

- GitLab: Merge Request Hook с `object_kind = merge_request` и
  `object_attributes.action = merge` либо `object_attributes.state = merged`;
- GitHub: Pull Request Hook с `action = closed` и `pull_request.merged = true`.

Источник берётся из `source_branch` / `pull_request.head.ref`, цель — из
`target_branch` / `pull_request.base.ref`. Удаление запускается только когда цель
совпадает с `mainBranch` проекта.

Чтобы этот сценарий работал, в настройках вебхука GitLab должны быть включены
**Merge request events**, в GitHub — **Pull requests**. Без них остаётся fallback:
push в `mainBranch`, в котором адаптер распознаёт merge-коммит
(`Merge branch 'feature/x' into 'main'`, `Merge pull request #N from org/feature/x`).
Fallback зависит от формата сообщения коммита: при squash/rebase-мердже, merge
queue и нескольких слияниях в одном push он может не определить ветку, поэтому
события MR/PR предпочтительны.

### Durable-намерение `unit_otlozhennoe_deistvie`

Джоб вебхука ретраится ограниченное число раз, после чего событие исчезает.
Поэтому намерение пишется в таблицу: `unit_id`, `project_id`, `tip_sobitiya`,
`payload` (jsonb: ветка и `unixtime`), `created_at`, `attempts`,
`next_attempt_at`, `last_error`. Уникальный индекс `(unit_id, tip_sobitiya)`
делает повторный вебхук по той же ветке обновлением существующей строки, а не
дубликатом.

`VETKA_UDALENA` и `VETKA_SMERZHENA` вытесняют `KOD_OBNOVLEN`
([`OtlozhitDeistvieNadUnitomUseCase`](src/Unitman/Business/UseCase/Unit/OtlozhitDeistvieNadUnitomUseCase.php:23)):
удаление или слияние ветки отменяет смысл её обновления, поэтому строка
обновления по этому юниту удаляется. Два намерения удаления тоже
взаимоисключающие: второе заменяет первое, чтобы не запускать два процесса.
Обратное неверно: обновление не отменяет отложенное удаление.

### Когда намерение применяется

| Триггер | Где |
| --- | --- |
| Освобождение захвата | [`OsvoboditZanyatostUnitovUseCase`](src/Unitman/Business/UseCase/Unit/OsvoboditZanyatostUnitovUseCase.php:1) сразу вызывает применение |
| Завершение последней задачи процесса | [`ObrabotatProzesiUnitaUseCase`](src/Unitman/Business/UseCase/Unit/ObrabotatProzesiUnitaUseCase.php:1), когда `poluchitJobuDlyObravotki()` вернул `null` |
| Страховочный обход | [`OtlozhennieDeistviyaBackgroundJob`](src/Unitman/Infra/BackgroundJob/OtlozhennieDeistviya/OtlozhennieDeistviyaBackgroundJob.php:19) |

Все три входа ведут в
[`PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase`](src/Unitman/Business/UseCase/Unit/PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase.php:26),
поэтому ведут себя одинаково.

Фоновый job (`otlozhennie_deistviya`, задержка 10 с) разбирает пачку до 50
юнитов в одной транзакции с `FOR UPDATE SKIP LOCKED`, каждый юнит — во
вложенной транзакции (savepoint), чтобы падение на одном не откатывало пачку.
Он же чистит истёкшие захваты и намерения старше 24 часов.

### Ошибки и backoff

| Ситуация | Поведение |
| --- | --- |
| Юнит всё ещё занят (`unit.zanyat_hodom_opencode`) | Повтор с задержкой `min(30·2^attempts, 900)` секунд |
| Временный отказ агрегата (`unit.zanyat`, `unit.wait_another_proccess`) | То же: повтор с задержкой |
| Юнит удалён (`unit.udalen`) | Намерение удаляется: применять нечего |
| Юнита нет в event store | Намерение удаляется |
| Исчерпан бюджет `MAX_ATTEMPTS = 40` | Намерение удаляется, в лог пишется `ischerpan_budget` |
| Неподдерживаемый тип события | `unit.otlozhennoe_deistvie.tip_ne_podderzhan` |

40 попыток при задержке до 15 минут покрывают около десяти часов занятости:
дольше держать намерение бессмысленно, событие вебхука к тому моменту устарело.

---

## Сводная таблица валидации

| Блок | Обязателен | Ограничения |
| --- | --- | --- |
| `variables` | нет | `id`, `label`, `type`, `defaultValue`; для `collection` — `options: [{id, name}]`; `helper` — `projectId`, `service`, `port`, `suffix?`, `type?` |
| `prepare` | **да** | непустой список команд |
| `reset_prepare` | **да** | непустой список команд |
| `up` | **да** | непустой список команд |
| `down` | **да** | непустой список команд |
| `services` | **да** | имя сервиса обязательно; у порта — `port` (0–65535, кроме зарезервированного `UNITMAN_IDE_PORT`) и `type`; `startUri` необязателен |
| `actions` | нет | `id`, `name`, `commands` (непустой массив), `variables?`, `prepare?` |
| `unitman_volumes` | нет | `dir` (уникальный), `name`; дубликат `dir` → ошибка `unit.config.volumes.dir_is_not_unique` |

---

## Коды ошибок

| Код | Причина |
| --- | --- |
| `unit.config.prepare_is_empty` | Пустой блок `prepare` |
| `unit.config.resetPrepare_is_empty` | Пустой блок `reset_prepare` |
| `unit.config.up_is_empty` | Пустой блок `up` |
| `unit.config.down_is_empty` | Пустой блок `down` |
| `unit.config.services_is_empty` | Пустой блок `services` |
| `unit.config.port.type.is_empty` | У порта не указан `type` |
| `unit.config.port.invalid` | Некорректный `port` (вне 0–65535 или зарезервирован за редактором `UNITMAN_IDE_PORT`) |
| `unit.config.action.id_not_defined` | У действия нет `id` |
| `unit.config.action.name_not_defined` | У действия нет `name` |
| `unit.config.action.commands_not_defined` | У действия нет `commands` |
| `unit.config.action.commands_is_not_array` | `commands` действия — не массив |
| `unit.config.action.variables_is_not_array` | `variables` действия — не массив |
| `unit.config.volumes.invalid_item` | Элемент `unitman_volumes` — не объект |
| `unit.config.volumes.dir_is_empty` | У тома пустой `dir` |
| `unit.config.volumes.name_is_empty` | У тома пустое `name` |
| `unit.config.volumes.dir_is_not_unique` | Дубликат `dir` в `unitman_volumes` |
| `unit.config.pomoschnik.projectId_is_empty` | У хелпера пустой `projectId` |
| `unit.config.pomoschnik.service_is_empty` | У хелпера пустой `service` |
| `unit.config.pomoschnik.port_invalid` | Некорректный `port` хелпера |
| `unit.config.pomoschnik.type_invalid` | Недопустимый протокол в `type` хелпера |
| `unit.config_variable_factory.type_is_empty` | У переменной не указан `type` |
| `unit.config_variable_factory.type_invalid` | Неизвестный `type` переменной |
