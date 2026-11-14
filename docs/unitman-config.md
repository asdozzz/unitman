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

# ─── Сервисы стенда: порты (для proxy/ссылок) и кэш сборки ───
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
  php:
    cache:
      files:                   # кэш инвалидируется при изменении файлов
        - composer.lock
      paths:                   # эти каталоги сохраняются между сборками
        - vendor

# ─── Необязательный блок: стадия сборки образа ───
build:
  image: asdozzz/unitman-unit:latest   # образ, в котором собирается проект
  commands: []                         # доп. команды сборки

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
    cache:                  # необязательно
      files:                # инвалидируют кэш при изменении
        - composer.lock
      paths:                # сохраняются между сборками
        - vendor
```

Пустой блок — ошибка `unit.config.services_is_empty`.

Ссылка на порт сервиса строится по протоколу `type`:

| `type` | Шаблон ссылки |
| --- | --- |
| `http` (или не указан) | `{scheme}://{port}.{unit}.{project}.{proxyHost}` — через proxy host проекта |
| любой другой (`ws`, `tcp`, …) | `{type}://{unit}.{project}:{port}` — напрямую на юнит |

Протокол нормализуется (обрезка пробелов + нижний регистр). `startUri` дописывается в конец
ссылки для любого протокола.

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

### `build` — образ сборки

| Поле | По умолчанию | Описание |
| --- | --- | --- |
| `image` | `asdozzz/unitman-unit:latest` | Образ, в котором собирается проект |
| `commands` | `[]` | Дополнительные команды сборки |

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

Ссылка строится по тому же правилу, что и для сервисов юнита: для `http` — через proxy host
проекта, для остальных протоколов — `{type}://{unit}.{project}:{port}`. Вместо `startUri`
из конфига подставляется `suffix` из запроса хелпера.

При создании юнита клиент передаёт вместе со значением `targetUnitId`, и Unitman сохраняет
связь «переменная → целевой юнит», а также собирает ссылку на выбранный стенд.

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

## Сводная таблица валидации

| Блок | Обязателен | Ограничения |
| --- | --- | --- |
| `variables` | нет | `id`, `label`, `type`, `defaultValue`; для `collection` — `options: [{id, name}]`; `helper` — `projectId`, `service`, `port`, `suffix?`, `type?` |
| `prepare` | **да** | непустой список команд |
| `reset_prepare` | **да** | непустой список команд |
| `up` | **да** | непустой список команд |
| `down` | **да** | непустой список команд |
| `services` | **да** | имя сервиса обязательно; у порта — `port` (0–65535) и `type`; `startUri` необязателен |
| `actions` | нет | `id`, `name`, `commands` (непустой массив), `variables?`, `prepare?` |
| `build` | нет | `image` (по умолчанию `asdozzz/unitman-unit:latest`), `commands?` |
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
| `unit.config.port.invalid` | Некорректный `port` (вне 0–65535) |
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
