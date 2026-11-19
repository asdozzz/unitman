# Нагрузочные тесты Unitman

Набор повторяет методику baseline из [`plans/memory-baseline/report.md`](../../plans/memory-baseline/report.md)
и проверяет SLO по задержкам. Абсолютные значения на локальном стенде выше
production: стенд работает на dev-образе с `APP_ENV=dev`, Xdebug 3.4.2 и
xhprof, OPcache в CLI выключен. Сравнивать «до/после» нужно на одном и том же
образе.

## 1. Что должно быть готово

- Стенд запущен, RoadRunner отвечает, метрики доступны на `web:2112`
  (HTTP/Jobs) и `web:9091` (Temporal).
- Тестовый аккаунт с `ROLE_ADMIN` (используется `POST /api/login`).
- Контейнер Redis с именем `redis` (в нём лежит KV прогресса раннера).
- Docker-сеть стенда — `unitman_default`. Все команды k6 ниже запускаются
  в ней, поэтому имя `web` резолвится.
- На хосте: `docker`, `curl`, `jq` (для `upload-large.sh`).
- k6 на хосте не нужен — используется образ `grafana/k6`.

Проверка сети и контейнеров:

```sh
docker network ls | grep unitman_default
docker ps --format '{{.Names}}' | grep -E '^(web|redis)$'
```

## 2. Регистрация токена прогресса

`POST /api/runner/saveSteps` — публичный эндпоинт, но он принимает только
токены, зарегистрированные в KV (иначе `400 runner.unknown_progress_token`).
Токен пишется в Redis в формате PHP `serialize()`, который использует
RoadRunner KV: `s:<len>:"<unixtime>";`.

```sh
./tests/load/register-progress-token.sh loadtest-k6
```

Скрипт идемпотентен, печатает ключ, значение и TTL (7200 секунд). Можно
указать контейнер и docker-host:

```sh
./tests/load/register-progress-token.sh loadtest-k6 redis
./tests/load/register-progress-token.sh loadtest-k6 redis ssh://user@docker-host
```

Проверка вручную:

```sh
docker exec redis sh -c \
  'redis-cli --user "$REDIS_USER" --pass "$REDIS_USER_PASSWORD" --no-auth-warning GET runner.progress.token.loadtest-k6'
```

## 3. Переменные окружения

| Переменная | Назначение | По умолчанию |
|------------|------------|--------------|
| `UNITMAN_BASE_URL` | база API для k6 | `http://web:8080` |
| `UNITMAN_USER` | email тестового аккаунта с `ROLE_ADMIN` | — (обязательна) |
| `UNITMAN_PASSWORD` | пароль аккаунта | — (обязательна) |
| `RUNNER_PROGRESS_TOKEN` | токен, зарегистрированный в Redis | — (обязательна) |
| `RUNNER_CALLBACK_SECRET` | HMAC-секрет, если включена подпись `saveSteps` | пусто |
| `SAVE_STEPS_SCENARIO` | `small`, `medium`, `max` или `all` для `save-steps.js` | `all` |
| `LOAD_VUS` | число VU в `save-steps.js` | `5` |
| `LOAD_DURATION` | длительность каждого сценария `save-steps.js` | `30s` |

Имена `LOAD_*`/`SAVE_STEPS_*` выбраны намеренно: переменные с префиксом `K6_`
k6 трактует как собственные опции (`K6_VUS`, `K6_DURATION` перезаписывают
`scenarios` и отключают выбранные сценарии).

Пороги заданы в скриптах и могут быть переопределены без правки кода:

| Переменная | SLO по умолчанию |
|------------|------------------|
| `SLO_UNIT_LIST_P95_MS` | 200 |
| `SLO_UNIT_MOI_P95_MS` | 200 |
| `SLO_SAVESTEPS_SMALL_P95_MS` | 120 |
| `SLO_SAVESTEPS_MEDIUM_P95_MS` | 150 |
| `SLO_SAVESTEPS_MAX_P95_MS` | 350 |
| `SLO_UPLOAD_1M_S` | 0.2 |
| `SLO_UPLOAD_50M_S` | 5 |
| `SLO_UPLOAD_199M_S` | 20 |

Пример ослабления порога на шумном стенде:

```sh
-e SLO_UNIT_LIST_P95_MS=250
```

`setup()` падает с понятным сообщением, если логин не удался или
`RUNNER_PROGRESS_TOKEN` не задан.

## 4. Запуск k6

Все команды выполняются из корня репозитория. Каталог `tests/load`
монтируется в контейнер как `/scripts`.

### 4.1. Smoke

По одному запросу на эндпоинт: `unit/list`, `unit/moi`, `saveSteps`.
Нужен для проверки доступов и самих скриптов.

```sh
docker run --rm -i --network unitman_default \
  -v "$PWD/tests/load:/scripts" \
  -e UNITMAN_BASE_URL=http://web:8080 \
  -e UNITMAN_USER=admin@admin.ru \
  -e UNITMAN_PASSWORD='<password>' \
  -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
  grafana/k6 run --vus 1 --duration 5s /scripts/k6/smoke.js
```

### 4.2. `POST /api/unit/list` (SLO p95 ≤ 200 мс)

```sh
docker run --rm -i --network unitman_default \
  -v "$PWD/tests/load:/scripts" \
  -e UNITMAN_BASE_URL=http://web:8080 \
  -e UNITMAN_USER=admin@admin.ru \
  -e UNITMAN_PASSWORD='<password>' \
  -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
  grafana/k6 run --vus 5 --duration 30s /scripts/k6/unit-list.js
```

### 4.3. `POST /api/unit/moi` (SLO p95 ≤ 200 мс)

```sh
docker run --rm -i --network unitman_default \
  -v "$PWD/tests/load:/scripts" \
  -e UNITMAN_BASE_URL=http://web:8080 \
  -e UNITMAN_USER=admin@admin.ru \
  -e UNITMAN_PASSWORD='<password>' \
  -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
  grafana/k6 run --vus 5 --duration 30s /scripts/k6/unit-moi.js
```

### 4.4. `POST /api/runner/saveSteps`

Три сценария: `small` (10 шагов), `medium` (500 шагов), `max`
(400 шагов × 6 КБ ≈ 2,4 МиБ `stepsContent`). По умолчанию идут
последовательно: `small → medium → max`, по `LOAD_DURATION` каждый.

```sh
docker run --rm -i --network unitman_default \
  -v "$PWD/tests/load:/scripts" \
  -e UNITMAN_BASE_URL=http://web:8080 \
  -e UNITMAN_USER=admin@admin.ru \
  -e UNITMAN_PASSWORD='<password>' \
  -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
  grafana/k6 run /scripts/k6/save-steps.js
```

Один сценарий (так удобнее сравнивать с baseline, где `ab` запускался
отдельно на каждый payload):

```sh
# small
docker run --rm -i --network unitman_default \
  -v "$PWD/tests/load:/scripts" \
  -e UNITMAN_BASE_URL=http://web:8080 \
  -e UNITMAN_USER=admin@admin.ru \
  -e UNITMAN_PASSWORD='<password>' \
  -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
  -e SAVE_STEPS_SCENARIO=small -e LOAD_VUS=5 -e LOAD_DURATION=30s \
  grafana/k6 run /scripts/k6/save-steps.js

# medium
docker run --rm -i --network unitman_default \
  -v "$PWD/tests/load:/scripts" \
  -e UNITMAN_BASE_URL=http://web:8080 \
  -e UNITMAN_USER=admin@admin.ru \
  -e UNITMAN_PASSWORD='<password>' \
  -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
  -e SAVE_STEPS_SCENARIO=medium -e LOAD_VUS=5 -e LOAD_DURATION=30s \
  grafana/k6 run /scripts/k6/save-steps.js

# max (2,4 МиБ на запрос)
docker run --rm -i --network unitman_default \
  -v "$PWD/tests/load:/scripts" \
  -e UNITMAN_BASE_URL=http://web:8080 \
  -e UNITMAN_USER=admin@admin.ru \
  -e UNITMAN_PASSWORD='<password>' \
  -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
  -e SAVE_STEPS_SCENARIO=max -e LOAD_VUS=1 -e LOAD_DURATION=30s \
  grafana/k6 run /scripts/k6/save-steps.js
```

Токен живёт 7200 секунд. Если тест идёт дольше или стенд перезапускали,
зарегистрируйте его повторно.

## 5. Загрузка крупных файлов

k6 не используется: 199 МиБ тело в JS-скрипте не имеет смысла, а baseline
снимался `curl`. Скрипт создаёт zip с корректной сигнатурой, логинится и
загружает файлы через multipart, печатая `time_total`.

```sh
UNITMAN_BASE_URL=http://localhost:8082 \
UNITMAN_USER=admin@admin.ru \
UNITMAN_PASSWORD='<password>' \
./tests/load/upload-large.sh
```

Только отдельные размеры:

```sh
./tests/load/upload-large.sh 1 50
```

Порты: на хосте API опубликован на `8082` (`UNITMAN_API_PORT` из `.env`).
Если запускать скрипт внутри сети `unitman_default`, передайте
`UNITMAN_BASE_URL=http://web:8080`.

200 МиБ не входит в набор: `Content-Length` вместе с multipart-обёрткой
превышает `MEDIATEKA_MAX_FILE_SIZE`, и запрос отклоняется (известное
поведение из baseline, а не деградация).

## 6. Как читать результаты

- k6 печатает сводку в stdout: `http_req_duration`, `http_req_failed`,
  `checks` и кастомные `savesteps_*_duration`.
- **Exit code 99** — превышен хотя бы один threshold. Это ожидаемый способ
  «завалить» прогон при деградации; ненулевой код, отличный от 99, означает,
  что тест не смог выполниться (например, 107 — исключение в `setup()`:
  логин не удался или не задан `RUNNER_PROGRESS_TOKEN`).
- Exit code 0 — все пороги пройдены.
- Отчёты в файл: добавьте `--out json=results.json` или
  `--summary-export summary.json` (путь внутри контейнера; смонтируйте
  каталог, чтобы забрать файл). Например:

  ```sh
  -v "$PWD/tests/load/results:/results" --summary-export /results/save-steps.json
  ```

- Для корреляции с памятью откройте дашборд `Unitman memory & SLO`
  ([`.docker/grafana/dashboards/unitman_memory.json`](../../.docker/grafana/dashboards/unitman_memory.json)):
  там `rr_http_worker_memory_bytes` по воркерам, p95 HTTP и память Jobs/Temporal.

## 7. Ограничения

- Абсолютные числа выше production из-за Xdebug/xhprof и dev-окружения.
- Стенд шумный: 24 фоновых PHP-сервиса создают нагрузку на CPU. Для
  сравнения «до/после» делайте минимум три прогона и смотрите медиану p95.
- `max`-сценарий приближает HTTP-воркеры к лимиту `max_worker_memory: 100`
  (baseline: пиковый RSS 97,3 МБ) — это ожидаемо и является предметом
  оптимизаций, а не ошибкой теста.
