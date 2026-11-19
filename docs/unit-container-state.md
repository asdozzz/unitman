# Состояние контейнеров юнитов и health-push

Раннер периодически сверяет реальное состояние контейнеров своих юнитов с тем,
что ожидает unitman. При расхождении unitman сохраняет признаки mismatch в
read-модели списка юнитов и уведомляет фронтенд через Centrifugo. После того как
раннер подтверждает, что состояние снова совпадает, признаки очищаются.

Документ описывает контракт для фронтенда: поля payload'а, семантику значений,
события Centrifugo и рекомендации по отображению.

## 1. Схема потока

```
runner (Go)                 unitman (PHP)               Centrifugo            фронт
    │                            │                         │                    │
    │  POST /api/runner/health   │                         │                    │
    │ ─────────────────────────► │  (health, раз в минуту)  │                    │
    │                            │                         │                    │
    │  POST /api/runner/        │                         │                    │
    │  unitStateNotifications    │                         │                    │
    │ ─────────────────────────► │  обновляет payload      │                    │
    │                            │  jsonb юнита            │                    │
    │                            │                         │                    │
    │                            │  publish OBNOVLEN       │                    │
    │                            │  spisok_unitov ───────► │ ─────────────────► │
    │                            │                         │   перечитать       │
    │                            │                         │   список юнитов    │
```

Раннер:

- пишет собственное состояние в bbolt `runner-state.db`;
- сверяет ожидаемое и фактическое состояние раз в **10 минут**;
- отправляет health раз в **минуту**;
- очищает образы и builder-кэш раз в **2 часа** (без `--volumes`);
- напоминает о mismatch не чаще, чем раз в **30 минут**;
- при восстановлении присылает событие `resolved`.

## 2. Поля read-модели

Поля находятся в payload'е строки юнита в канале `spisok_unitov`. Ключи JSON —
ровно в camelCase.

| Поле | Тип | Смысл |
|------|-----|-------|
| `containerStateMismatch` | `bool` | `true`, если фактическое состояние контейнера расходится с ожидаемым. |
| `containerStateMismatchExpected` | `string\|null` | Ожидаемое состояние (`running` или `absent`); `null`, если mismatch нет. |
| `containerStateMismatchActual` | `string\|null` | Фактическое состояние (`running`, `stopped`, `absent`, `unknown`); `null`, если mismatch нет. |
| `containerStateMismatchDetectedAt` | `int\|null` | Время обнаружения расхождения, unix-секунды; `null`, если mismatch нет. |

## 3. Семантика состояний

### `expectedState`

| Значение | Смысл |
|----------|-------|
| `running` | Контейнер юнита должен быть запущен. |
| `absent` | Контейнера юнита быть не должно. |

### `actualState`

| Значение | Смысл |
|----------|-------|
| `running` | Контейнер фактически запущен. |
| `stopped` | Контейнер существует, но остановлен. |
| `absent` | Контейнера фактически нет. |
| `unknown` | Раннер не смог определить фактическое состояние. |

Mismatch фиксируется, когда `actualState` не соответствует `expectedState`
(например, ожидалось `running`, а фактически `absent` или `unknown`).

## 4. Примеры JSON

### Строка юнита с mismatch

```json
{
  "id": "0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b",
  "name": "feature-login",
  "projectId": "0192a1b2-0000-7000-8000-000000000001",
  "containerStateMismatch": true,
  "containerStateMismatchExpected": "running",
  "containerStateMismatchActual": "absent",
  "containerStateMismatchDetectedAt": 1730000000
}
```

### Строка юнита после `resolved`

```json
{
  "id": "0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b",
  "name": "feature-login",
  "projectId": "0192a1b2-0000-7000-8000-000000000001",
  "containerStateMismatch": false,
  "containerStateMismatchExpected": null,
  "containerStateMismatchActual": null,
  "containerStateMismatchDetectedAt": null
}
```

## 5. Событие `OBNOVLEN` в канале `spisok_unitov`

При изменении признаков mismatch unitman публикует событие типа `OBNOVLEN`
(константа `WebsocketUnitEventReadModel::OBNOVLEN`) в канал `spisok_unitov`.

```json
{
  "type": "OBNOVLEN"
}
```

Что делать фронту:

1. По событию `OBNOVLEN` перечитать список юнитов (pull-запрос списка).
2. Обновить карточки юнитов на основе новых полей `containerStateMismatch*`.
3. Не пытаться восстановить состояние из самого события — оно лишь сигнал
   «данные устарели».

## 6. Рекомендации фронту

- Показывать на карточке юнита бейдж или баннер, если
  `containerStateMismatch === true`.
- Текст бейджа формировать из полей, например:
  «Контейнер не запущен, ожидалось `running`, фактически `absent`»
  (подставлять `containerStateMismatchExpected` и `containerStateMismatchActual`).
- Время обнаружения брать из `containerStateMismatchDetectedAt`
  (unix-секунды) и отображать в локальном часовом поясе пользователя.
- После события `resolved` поля очищаются (`false` / `null`) — бейдж должен
  исчезнуть без дополнительных действий пользователя.
- Не блокировать действия над юнитом из-за mismatch — это индикатор, а не
  запрет.

## 7. Источники данных (для справки)

### `POST /api/runner/health`

Публичный эндпоинт. Тело ≤ **2 МиБ**, ответ — `true`. Подпись:
`X-Runner-Signature = hex(HMAC-SHA256(rawBody, RUNNER_CALLBACK_SECRET))`.

```json
{
  "runnerId": "runner-1",
  "taskQueue": "runner",
  "active": true,
  "dockerStats": {},
  "memInfo": {},
  "unixtime": 1730000000
}
```

### `POST /api/runner/unitStateNotifications`

Публичный эндпоинт. Тело ≤ **1 МиБ**, та же подпись, ответ — `true`.

```json
{
  "runnerId": "runner-1",
  "notifications": [
    {
      "unitId": "0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b",
      "projectId": "0192a1b2-0000-7000-8000-000000000001",
      "unitName": "feature-login",
      "projectName": "shop",
      "expectedState": "running",
      "actualState": "absent",
      "eventType": "mismatch",
      "detectedAt": 1730000000
    }
  ]
}
```

`eventType` принимает значения `mismatch` (расхождение обнаружено) и `resolved`
(состояние снова совпадает). По `resolved` unitman очищает поля
`containerStateMismatch*`.

## 8. Хранение

Поля `containerStateMismatch*` добавляются в `payload` (jsonb) без миграций.
Старые записи читаются с дефолтами: `containerStateMismatch = false`,
остальные поля — `null`. Поэтому отсутствие ключей в payload'е трактуется как
«mismatch нет».
