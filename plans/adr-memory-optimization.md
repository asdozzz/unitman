# ADR: снижение потребления памяти Unitman (сборка, пулы, фоновые задачи, edge)

- **Статус:** Accepted, реализовано на dev-стенде; production-замер открыт.
- **Дата:** 2026-11-19.
- **Контекст:** итоги baseline в [`plans/memory-baseline/report.md`](memory-baseline/report.md), план — [`plans/memory-footprint-reduction.md`](memory-footprint-reduction.md).
- **Область:** рантайм и production-образ, фоновые задачи, роли RoadRunner, пиковые аллокации (`saveSteps`, Mediateka, webhook), Go edge-сервис, нагрузочные SLO. Домен и Event Sourcing остаются в PHP/PostgreSQL.

## 1. Контекст

Unitman — Symfony 7 + RoadRunner 3 + PostgreSQL. Каждый воркер RoadRunner поднимает полный Symfony-контейнер и живёт до планового или аварийного рестарта, поэтому память приложения определяется не «тяжёлым кодом», а **количеством одновременно живых PHP-процессов и их пиковыми аллокациями**.

Замер до оптимизаций (dev-образ, `APP_ENV=dev`, Xdebug 3.4.2, xhprof, OPcache в CLI выключен):

| Показатель | Значение |
|------------|----------|
| PHP/RoadRunner-процессов в idle | 70 |
| PSS в idle | 1938–1952 МБ (снимки 02–04) |
| PSS после нагрузки | 2314–2417 МБ |
| `docker stats` в idle / после нагрузки | 1.93 GiB / 2.31–2.42 GiB |
| Фоновые сервисы | 24 процесса, 857 МБ PSS (44 % всей памяти) |
| Пик HTTP-воркера после `saveSteps` 2,4 МБ | RSS 97,3 МБ при `max_worker_memory: 100` |
| `rr serve` после загрузки 199 МБ | рост с 89 до ~310 МБ, память не возвращается после паузы |

Причины, подтверждённые замером:

1. 15 фоновых задач запускались отдельными RR-сервисами; `ochered_unitov` — 10 процессов, остальные по одному (24 процесса суммарно).
2. Один `rr serve` совмещал HTTP, Jobs, Temporal и динамические сервисы: общий процесс-«свалка» и общий high-water mark.
3. `saveSteps` декодировал payload 2,4 МБ в массив и размножал его при нормализации; HTTP-пул рос с 253 до 606 МБ PSS.
4. Загрузка файла шла через PHP-воркер, а `Content-Length` вместе с multipart-обёрткой отклонял файл ровно 200 МБ.
5. Production-образ содержал xdebug/xhprof, а в CLI-воркерах OPcache/JIT/preload были настроены неоптимально.

## 2. Решения

### D1. Production-образ без отладочных расширений

**Решение.** В [`Dockerfile.dist`](../Dockerfile.dist) не устанавливаются xdebug и xhprof; зависимости ставятся командой `composer install --no-scripts --no-interaction --no-dev --classmap-authoritative`, затем `composer dump-autoload --optimize --classmap-authoritative`. xdebug остаётся только в dev-образе [`Dockerfile`](../Dockerfile).

**Почему.** Отладочные расширения увеличивают RSS каждого воркера и замедляют каждый вызов; `--no-dev` убирает phpunit/psalm/web-profiler, а `--classmap-authoritative` исключает `stat()` по файловой системе на каждый автозагружаемый класс.

**Откат.** Вернуть предыдущий тег production-образа или предыдущую версию `Dockerfile.dist`; dev-стенд не затрагивается.

### D2. OPcache/JIT/preload и PHP-настройки для CLI-воркеров

**Решение.** В [`.docker/php/settings.ini`](../.docker/php/settings.ini):

- `memory_limit = 256M`, `post_max_size = 210M`, `upload_max_filesize = 210M`;
- `opcache.enable = 1`, `opcache.enable_cli = 1`, `opcache.memory_consumption = 64`, `opcache.interned_strings_buffer = 8`, `opcache.max_accelerated_files = 20000`;
- `opcache.validate_timestamps = 0`, `opcache.save_comments = 0`;
- `opcache.jit = off`, `opcache.preload =`;
- `realpath_cache_size = 4096K`, `realpath_cache_ttl = 600`, `zend.assertions = -1`.

**Почему.** RoadRunner работает в CLI SAPI: без `opcache.enable_cli=1` байткод воркеров не кэшируется вообще. JIT не даёт выигрыша для I/O-нагрузки, но требует буфер на каждый процесс. Preload в CLI не разделяется между процессами RoadRunner: каждый воркер платит за него RSS, а master-процесса PHP-FPM, который делят воркеры, здесь нет. `save_comments=0` экономит память, потому что атрибуты PHP 8 читаются рефлексией без docblock'ов.

**Откат.** Вернуть предыдущую версию `settings.ini`; изменения не требуют миграций и пересборки кода.

### D3. Три роли RoadRunner вместо одного процесса

**Решение.** Один образ запускается тремя командами:

| Роль | Конфиг | Что включает | RPC | Метрики |
|------|--------|--------------|-----|---------|
| `web` | [`.rr.http.yaml`](../.rr.http.yaml) | только HTTP-пул | 6001 | `:2112` |
| `web-worker` | [`.rr.worker.yaml`](../.rr.worker.yaml) | Jobs + RR services планировщика | 6002 | `:2113` |
| `web-temporal` | [`.rr.temporal.yaml`](../.rr.temporal.yaml) | Temporal activities | 6003 | `:9091` |

Сервисы описаны в [`common-services.yml`](../common-services.yml:1). Старый `.rr.yaml` остаётся fallback-конфигом одиночного процесса.

**Почему.** Разделение даёт независимые лимиты и рестарты (падение Jobs не трогает HTTP), прозрачный `docker stats` по ролям и возможность вынести worker-роль на отдельный узел без изменения кода.

**Откат.** `docker compose stop web web-worker web-temporal && docker compose --profile rr-single up -d web-single` — профиль `rr-single` поднимает прежний `rr serve -c .rr.yaml` на тех же портах.

### D4. Консолидация 15 фоновых задач в 3 планировщика

**Решение.** `BackgroundJobInterface::getGroup()` возвращает одну из групп `projections`, `producers`, `timers` ([`BackgroundJobGroup`](../src/BackgroundJob/Infra/Service/BackgroundJobGroup.php)); команда `app:services:scheduler <group>` держит один процесс на группу ([`SchedulerCommand`](../src/BackgroundJob/Infra/Cli/SchedulerCommand.php)). `BackgroundJobService` при включённом планировщике создаёт три RR-сервиса `background_jobs_*` вместо сервиса на задачу.

| Группа | Задачи |
|--------|--------|
| `projections` | `unit_runner_jobs`, `project_runner_jobs`, `websocket_unit_event`, `websocket_project_event`, `webhook_event_puller`, `statistika_po_proektu` |
| `producers` | `ochered_unitov`, `ochered_proektov`, `otpravka_zadach_v_ochered`, `otlozhennie_deistviya` |
| `timers` | `runner_health_check`, `ochistka_dockera`, `udalenie_neaktivnih_unitov`, `opencode_session_unit_sync`, `webhook_event_sender` |

Планировщик хранит `nextRunAt` по каждой задаче, ловит `Throwable` отдельно по задаче, применяет `getDelay()` и существующие блокировки `LockService`; семафор `processNum` заменён конкуренцией через блокировки/очередь.

**Почему.** 24 процесса фоновых сервисов давали 857 МБ PSS — 44 % памяти. Один процесс на группу сохраняет интервалы и поведение, но убирает дублирование Symfony-контейнера.

**Откат.** `BACKGROUND_JOB_SCHEDULER=0`, затем `php bin/console app:jobs stop && php bin/console app:jobs start` — возвращается режим «сервис на задачу». Флаг описан в [`.env.dist`](../.env.dist:67).

### D5. Mediateka: presigned PUT + finalize и точный лимит

**Решение.** Добавлены аддитивные эндпоинты:

- `POST /api/mediateka/presign` — проверяет те же правила, что `/upload`, но не принимает тело; возвращает presigned PUT URL, заголовки и подписанный `uploadToken`;
- `POST /api/mediateka/finalize` — проверяет подпись и TTL токена, делает HEAD объекта в MinIO и требует, чтобы фактический размер совпал с заявленным при presign.

Лимит в [`ZagruzitFaylUnitaUseCase`](../src/Mediateka/Business/UseCase/ZagruzitFaylUnitaUseCase.php:90) проверяется как `contentLength > maxFileSize + CONTENT_LENGTH_ALLOWANCE`, где допуск — 1 МиБ (`1048576`). Старый `POST /api/mediateka/upload` сохранён как совместимый путь.

**Почему.** Прямая загрузка в MinIO убирает большой файл из PHP-воркера и из процесса `rr serve` (наблюдалось +~310 МБ после 199 МБ). Допуск в 1 МиБ компенсирует multipart-обёртку, поэтому файл ровно 200 МБ больше не отклоняется, а файл больше лимита по-прежнему отклоняется. `finalize` не даёт записать в реестр объект не того размера.

**Откат.** Клиенты продолжают использовать `/upload`; presigned-эндпоинты аддитивны и не меняют существующий контракт. При необходимости маршруты можно снять без миграции данных.

### D6. `saveSteps`: одно декодирование и явные лимиты

**Решение.** В [`RunnerController`](../src/Runner/Infra/Controller/RunnerController.php:31):

- `MAX_STEPS_CONTENT_BYTES = 2560 * 1024` — проверка `strlen` **до** `json_decode`;
- `MAX_STEPS = 5000`;
- `MAX_STEP_RESPONSE_BYTES = 65536` на `response` одного шага;
- payload декодируется один раз, нормализуется один раз и в этом виде используется и для KV, и для публикации.

**Почему.** Исходный путь декодировал 2,4 МБ JSON в массив, затем делал `array_map`-нормализацию и повторную сериализацию. Пик в 5–10 раз выше payload подводил HTTP-воркеры к рестарту по `max_worker_memory: 100` (пик RSS 97,3 МБ).

**Откат.** Ослабить константы лимитов или вернуть предыдущую реализацию контроллера; для аварийного отключения тяжёлого PHP-пути используется Go progress gateway (D8).

### D7. Webhook: лимит до декодирования и одно декодирование

**Решение.** [`SobitiePayloadDecoder`](../src/Unitman/Infra/Jobs/SobitiyaIzHranilisha/SobitiePayloadDecoder.php:26) проверяет `strlen` до `json_decode` и отклоняет событие больше `MAX_EVENT_PAYLOAD_BYTES = 5 * 1024 * 1024` со стабильным кодом. [`SobitiaIzHranilishaActivity`](../src/Unitman/Infra/Jobs/SobitiyaIzHranilisha/SobitiaIzHranilishaActivity.php) декодирует payload один раз и передаёт дальше уже готовый объект, а не строку.

**Почему.** Большой push-payload размножался в памяти на каждом повторном декодировании в цепочке обработки; лимит до декодирования отсекает всплески до аллокации.

**Откат.** Поднять лимит или вернуть прежний декодер; контракт события и event store не меняются.

### D8. Go edge: подкоманды progress/media/webhook/telemetry

**Решение.** Один бинарник `go/edge` с четырьмя подкомандами, по контейнеру на подкоманду, и канареечный nginx-шлюз:

| Сервис | Подкоманда | Назначение |
|--------|-----------|------------|
| `edge-progress` | `progress` | `saveSteps`, чтение прогресса, HMAC `X-Runner-Signature`, запись в Redis `runner.progress.*`, публикация в Centrifugo |
| `edge-media` | `media` | потоковые `PUT/GET/DELETE /media/{key}` в MinIO/S3, ZIP-сигнатура, лимит 200 МиБ |
| `edge-webhook` | `webhook` | приём GitLab/GitHub, проверка подписи и лимита, проксирование тела в PHP |
| `edge-telemetry` | `telemetry` | `GET /metrics` по Docker-метрикам для Prometheus |
| `edge-gateway` | nginx | канареечная маршрутизация трёх потоков в Go, остальное — в PHP |

Контракты сохранены: те же пути и коды, тот же заголовок `X-Runner-Signature`, те же ключи Redis и каналы Centrifugo; Go не пишет в доменные таблицы и event store. Документация — [`docs/edge-service.md`](../docs/edge-service.md).

**Почему.** Progress, media, webhook и telemetry — stateless-части с высоким трафиком и большими payload; в Go они не занимают PHP-воркеры и не раздувают `rr serve`.

**Откат.** Профиль `edge` opt-in: достаточно остановить пять контейнеров и вернуть раннер/webhook'и на PHP-порт 8082. PHP-путь полностью функционален.

### D9. Канареечный шлюз и Prometheus

**Решение.** [`edge-gateway`](../.docker/nginx/edge.conf) публикуется на `EDGE_GATEWAY_PORT` (по умолчанию 8090) и маршрутизирует `saveSteps`, `project/*/hook` и `/media/*` в Go, всё остальное — в `web:8080`. `edge-telemetry` добавлен в [`.docker/prometheus/prometheus.yml`](../.docker/prometheus/prometheus.yml). Профиль `edge` по умолчанию выключен.

**Почему.** Канарейка позволяет переключить раннер и webhook'и без изменения основного API-порта; при выключенном профиле цель Prometheus `edge-telemetry` будет `down` — это ожидаемо и не влияет на существующие цели web/worker/temporal.

**Откат.** `docker compose --profile edge stop edge-progress edge-media edge-webhook edge-telemetry edge-gateway`; трафик возвращается на 8082.

### D10. k6-набор и SLO

**Решение.** Набор [`tests/load`](../tests/load/README.md) воспроизводит baseline: `smoke`, `unit/list`, `unit/moi`, `saveSteps` (small/medium/max), загрузки 1/50/199 МБ. Пороги заданы в k6-скриптах и переопределяются переменными `SLO_*`:

| Метрика | SLO |
|---------|-----|
| `unit/list` p95 | ≤ 200 мс |
| `unit/moi` p95 | ≤ 200 мс |
| `saveSteps` 10 шагов p95 | ≤ 120 мс |
| `saveSteps` 500 шагов p95 | ≤ 150 мс |
| `saveSteps` 2,4 МБ p95 | ≤ 350 мс |
| Загрузка 1 МБ / 50 МБ / 199 МБ | ≤ 0,2 с / ≤ 5 с / ≤ 20 с |
| Ошибки запросов | `rate == 0` |

**Почему.** Без воспроизводимого набора «до/после» любые изменения памяти нельзя отделить от шума dev-стенда.

**Откат.** Набор тестовый, на production-код не влияет.

## 3. Измеренный результат

### 3.1. Снимок после включения планировщика

Снимок [`plans/memory-baseline/13-after-scheduler.csv`](memory-baseline/13-after-scheduler.csv) (2026-11-19T05:58Z, одиночный RR-процесс с тремя scheduler-группами):

| Роль | Процессов | PSS, МБ |
|------|-----------|---------|
| HTTP | 32 | 886,0 |
| Temporal | 13 | 510,2 |
| Scheduler (`projections`/`producers`/`timers`) | 3 | 137,6 |
| `rr serve` | 1 | 92,7 |
| **Итого** | **49** | **1626,6** |

Итого: **49 процессов вместо 70**, **1626,6 МБ PSS вместо 1952,3 МБ** в idle (−16,7 %). Фоновые сервисы: **3 процесса вместо 24**.

### 3.2. Разделение ролей RoadRunner

После разделения на три роли `docker stats` даёт:

| Контейнер | Память |
|-----------|--------|
| `web` | 468,5 MiB |
| `web-worker` | 772,6 MiB |
| `web-temporal` | 573,3 MiB |
| **Итого** | **~1814 MiB (~1,77 GiB)** |

Сумма `docker stats` выше PSS, потому что общие страницы (OPcache, библиотеки, autoload-карта) учитываются в каждом контейнере отдельно. PSS-снимок и `docker stats` — разные метрики; для сравнения «до/после» используется один и тот же метод.

### 3.3. Edge-профиль

Edge-профиль собран и прошёл smoke через канареечный шлюз, после чего **остановлен**. Он остаётся **opt-in**: обычный `docker compose up -d` поднимает только PHP/RoadRunner-стек, дефолтный API остаётся на `UNITMAN_API_PORT` (8082). В текущих замерах памяти edge-процессы не участвуют.

### 3.4. Ограничения результата

- Замеры сделаны на dev-образе с Xdebug/xhprof; абсолютные значения выше production.
- Снимок 13 снят на fallback-конфиге `.rr.yaml` с 32 HTTP-воркерами; роли в `.rr.*.yaml` параметризованы (`RR_HTTP_NUM_WORKERS`, `RR_JOBS_NUM_WORKERS`, `RR_TEMPORAL_NUM_WORKERS`) и могут быть уменьшены после замера дрейфа и финального k6-сравнения.
- Цель ≤ 1,2 ГБ PSS в idle на dev-образе не достигнута; production-замер образа без xdebug/xhprof остаётся открытым пунктом.

## 4. Компромиссы и отклонённые альтернативы

| Альтернатива | Почему отклонена / отложена |
|--------------|------------------------------|
| Переписать Temporal workflow/activity на Go | Высокий риск несовместимости и реплея; выигрыш достигается уже снижением `RR_TEMPORAL_NUM_WORKERS`. Оставлено как будущая опция. |
| Перенести домен и Event Sourcing в Go | Недопустимы две модели записи; единственный источник истины остаётся в PHP/PostgreSQL. |
| Включить JIT | Для I/O-нагрузки нет прироста, но растёт RSS и время старта каждого CLI-воркера. |
| Включить `opcache.preload` | В CLI-воркерах RoadRunner preload не разделяется между процессами; каждый воркер платит памятью без выигрыша. |
| Удалить TwigBundle | Twig нужен для `/api/doc` и dev-профайлера; выигрыш мал относительно риска сломать документацию. |
| Снизить пулы до целевых значений сразу | Без финального k6-сравнения и замера дрейфа это повышает риск роста p95. Сначала устранены пики и процессы, затем — тонкая настройка пулов. |
| Заменить `/upload` на presigned полностью | Ломает существующих клиентов и интеграции; оставлен совместимый путь. |
| Включить edge-профиль по умолчанию | Увеличивает число контейнеров и требует отдельного замера; канарейка и opt-in дают безопасный откат. |

## 5. План раскатки и отката

| Изменение | Раскатка | Откат |
|-----------|----------|-------|
| Production-образ без xdebug/xhprof, `--no-dev` | Собрать и выкатить новый тег; проверить `php -m` | Вернуть предыдущий тег |
| OPcache/realpath/assertions | Доставить `settings.ini` в образ | Вернуть предыдущий `settings.ini` |
| Роли RoadRunner | Поднять `web`, `web-worker`, `web-temporal`; проверить `rr workers` и метрики 2112/2113/9091 | `--profile rr-single` + `web-single`, остановить три роли |
| Планировщик фоновых задач | `BACKGROUND_JOB_SCHEDULER=1` (по умолчанию), `app:jobs stop && app:jobs start` | `BACKGROUND_JOB_SCHEDULER=0`, затем `app:jobs stop && app:jobs start` |
| Presigned upload | Выкатить аддитивные `/presign` и `/finalize`; клиентов переводить постепенно | Клиенты возвращаются на `/upload`; маршруты можно снять |
| `saveSteps` лимиты | Выкатить вместе с контроллером; проверить контрактные тесты | Ослабить константы; при необходимости перевести раннер на Go progress |
| Webhook лимит 5 МБ | Выкатить декодер; проверить повторную доставку при превышении | Поднять лимит или вернуть прежний декодер |
| Edge-профиль | `docker compose --profile edge up -d --build`, перевести раннер/webhook на 8090 | Остановить пять контейнеров, вернуть трафик на 8082 |
| Prometheus edge-target | Добавить цель `edge-telemetry:8083` | Убрать цель; `down` при выключенном профиле ожидаем |

## 6. Открытые вопросы

1. **Полный суточный дрейф** — сокращённый цикл 600 с уже снят: HTTP +108 МиБ после `saveSteps`, worker/temporal стабильны ([`drift-report.md`](memory-baseline/drift-report.md)). Нужен полный цикл на production-образе.
2. **Замер production-образа** без xdebug/xhprof — подтвердить, что базовый PSS воркера ниже dev-значений, и только после этого снижать `memory_limit` и пулы.
3. **Снижение пулов** — финальный k6 уложился в SLO (`saveSteps` 2,4 МБ p95 149,7 мс против 219 мс; `unit/list` 46 мс; `unit/moi` 43 мс), поэтому можно переходить к `RR_HTTP_NUM_WORKERS`/`RR_JOBS_NUM_WORKERS`/`RR_TEMPORAL_NUM_WORKERS` после production-замера.
4. **Go Temporal worker** — рассматривать только как отдельную итерацию с контрактными тестами и планом реплея workflow.
5. **Edge в production** — канареечный перевод раннера и webhook'ов после замера памяти edge-процессов на целевом стенде. Сейчас профиль `edge` остановлен и остаётся opt-in.

## 7. Ссылки

- Baseline: [`plans/memory-baseline/report.md`](memory-baseline/report.md)
- План: [`plans/memory-footprint-reduction.md`](memory-footprint-reduction.md)
- Протокол замера: [`plans/memory-baseline-protocol.md`](memory-baseline-protocol.md)
- Edge-сервис: [`docs/edge-service.md`](../docs/edge-service.md)
- Нагрузочные тесты: [`tests/load/README.md`](../tests/load/README.md)
- Роли RR: [`.rr.http.yaml`](../.rr.http.yaml), [`.rr.worker.yaml`](../.rr.worker.yaml), [`.rr.temporal.yaml`](../.rr.temporal.yaml)
- Планировщик: [`BackgroundJobScheduler`](../src/BackgroundJob/Infra/Service/BackgroundJobScheduler.php), [`SchedulerCommand`](../src/BackgroundJob/Infra/Cli/SchedulerCommand.php)
