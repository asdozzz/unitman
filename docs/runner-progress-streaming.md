# Прогресс выполнения команд runner'а в реальном времени

Ранее результат задачи runner'а был доступен только после завершения всей
Temporal-активити. Теперь промежуточный прогресс доступен двумя способами:

- **push** — подписка на канал Centrifugo `unit_progress#<jobId>`;
- **pull** — запрос `POST /api/unit/poluchitProgressZadachiRunnera`.

Оба источника отдают одинаковую структуру снапшота, поэтому фронтенд может
переключаться между ними без изменения модели данных.

## 1. Контракт с Go-раннером

Единственное требование: `responseId` в `saveSteps` должен совпадать с
идентификатором workflow (`jobId`), под которым юнит знает свою задачу.

```go
token := activity.GetInfo(ctx).WorkflowExecution.ID

// после каждой выполненной команды — накопленный полный список шагов:
// POST /api/runner/saveSteps
// X-Runner-Signature: <hex HMAC-SHA256(token + stepsContent, RUNNER_CALLBACK_SECRET)>
// {
//   "responseId": token,
//   "stepsContent": "[{\"command\":\"docker build ...\",\"response\":\"...\",\"success\":true,\"unixtime\":1730000000,\"UnixtimeStart\":1729999975}]",
//   "finished": false,
//   "success": true
// }
```

Правила:

- В элементе шага `unixtime` — **время окончания** команды (unix-секунды,
  обязательное), `UnixtimeStart` — **необязательное время начала**. Раннер
  может присылать его как `UnixtimeStart` (PascalCase, канонический вариант)
  или `unixtimeStart` (camelCase) — PHP-сторона принимает оба и нормализует
  к PascalCase. Если ключа нет, шаг считается шагом без известного времени
  начала; невалидное значение (не число, отрицательное, больше `unixtime`)
  отбрасывается без ошибки запроса.
- `stepsContent` — **полный** снапшот шагов на текущий момент (не дельта).
- `finished: true` — в последнем вызове по задаче; UI закрывает прогресс не
  дожидаясь события `OBNOVLEN`.
- `success` — итоговый признак успеха; осмыслен вместе с `finished`.
- Токен должен быть предварительно зарегистрирован: `RunnerApi::zaregistrirovatProgressToken()`
  вызывается до старта workflow. Незарегистрированный токен отклоняется с
  `runner.unknown_progress_token`.
- Размер `stepsContent` — не более 2560 КиБ (`runner.steps_too_large`).
- `stepsContent` обязан быть JSON-массивом объектов (`runner.steps_malformed`).
  Не-массивные элементы отбрасываются.

### Подпись запроса

Заголовок `X-Runner-Signature`:

```
hex( hash_hmac('sha256', responseId . stepsContent, RUNNER_CALLBACK_SECRET) )
```

- Если `RUNNER_CALLBACK_SECRET` пуст, проверка отключена — режим поэтапного
  раската, чтобы старый раннер мог работать без изменений.
- Подпись сравнивается через `hash_equals`, регистр hex-символов важен.

### Совместимость при деплое

Вариант с `activity.GetInfo(ctx)` не меняет сигнатуру активити и payload,
поэтому безопасен для реплея уже запущенных workflow. Всё равно рекомендуется
деплоить в окно без активных сборок и делать `rr reset`.

Fallback, если `WorkflowExecution.ID` недоступен в конкретной активити:
передавать токен вторым аргументом `Workflow::executeActivity('X', [$command, $workflowId])`.
Это ломает сигнатуру активити — применять точечно.

## 2. Контракт для фронтенда

### Канал

Centrifugo, канал `unit_progress#<jobId>`.

### Payload (идентичен `data` pull-ответа)

```json
{
  "jobId": "0192...",
  "steps": [
    {
      "command": "docker build ...",
      "response": "...",
      "success": true,
      "unixtime": 1730000000,
      "unixtimeStart": 1729999975
    },
    {
      "command": "docker push ...",
      "response": "...",
      "success": true,
      "unixtime": 1730000100
    }
  ],
  "finished": false,
  "success": true,
  "updatedAt": 1730000100
}
```

`unixtime` — время окончания шага (всегда присутствует), `unixtimeStart` —
необязательное время начала. Ключ `unixtimeStart` **не эмитится вовсе**, если
время начала неизвестно (старый Go-раннер, шаги из уже сохранённых событий,
API-фолбэки) — payload таких шагов остаётся байт-в-байт прежним, поэтому
пересборка проекций не требуется.

### Поведение UI

1. На открытии панели прогресса — subscribe на `unit_progress#<jobId>` и один
   pull-запрос `POST /api/unit/poluchitProgressZadachiRunnera` для начального
   состояния (закрывает гонку подписки).
2. Снапшот всегда полный — просто перезаписывать список шагов.
3. `finished: true` либо событие `OBNOVLEN` в `spisok_unitov` — отписаться и
   перечитать итог задачи.
4. Длительность шага: `unixtime - unixtimeStart` при наличии поля. Если поля
   нет — показывать только время окончания (прежнее поведение), не подставляя
   нулевую или отрицательную длительность.

### Pull-эндпоинт

`POST /api/unit/poluchitProgressZadachiRunnera`

```json
{ "prozesId": "<id процесса юнита>", "zadachaId": "<jobId>" }
```

Ответ — конверт `{"status":"success","data":{ ...payload выше... }}`.
Если раннер ещё не присылал шагов, `data.steps` пуст, `finished=false`,
`updatedAt=0`. Чужая задача (не принадлежащая процессу) — `unit.runner_job_not_found`.

## 3. Троттлинг и защита от флуда

Сборка и запуск генерируют десятки шагов за секунды, поэтому:

- push-публикация — не чаще одного раза в **250 мс** на токен
  (маркер `runner.progress.lastpub.<token>`, TTL 1 с);
- при пропуске публикации KV всё равно обновляется — pull-эндпоинт отдаст
  актуальные данные;
- `finished: true` публикуется всегда, минуя троттлинг;
- ошибки Centrifugo проглатываются и не ломают ответ раннеру;
- TTL прогресса в KV — **2 часа**: прогресс не источник истины и не должен
  храниться вечно.

## 4. Хранение

RoadRunner KV (Redis) под префиксами:

| Ключ | TTL | Назначение |
|------|-----|------------|
| `runner.progress.<token>` | 2 ч | сериализованный снапшот `RunnerProgress` |
| `runner.progress.token.<token>` | 2 ч | признак регистрации токена |
| `runner.progress.lastpub.<token>` | 1 с | маркер троттлинга публикации |
