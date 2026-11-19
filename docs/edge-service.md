# Edge-сервисы (Go) и канареечный шлюз

Go-сервис `go/edge` собирается в образ `unitman-edge:dev` и запускает один из
четырёх подкомандов. Каждый подкоманд слушает свой порт, поэтому в compose это
четыре отдельных контейнера. Все они и канареечный шлюз `edge-gateway` включены
в профиль **`edge`** и по умолчанию **не запускаются**: обычный
`docker compose up -d` поднимает только PHP/RoadRunner-стек, а дефолтный API
остаётся на `UNITMAN_API_PORT` (8082).

## 1. Что делает каждый подкоманд

| Сервис compose   | Команда               | Порт  | Назначение |
|------------------|-----------------------|-------|------------|
| `edge-progress`  | `progress`            | 8081  | `POST /api/runner/saveSteps` (подпись `X-Runner-Signature`, лимиты, запись в Redis `runner.progress.*`), `GET /api/runner/progress/{responseId}`, публикация в Centrifugo `unit_progress#<jobId>`. |
| `edge-media`     | `media`               | 8082  | Потоковые `PUT/GET/DELETE /media/{key...}` в MinIO/S3 без буферизации в памяти. Проверяет ZIP-сигнатуру, ограничивает размер (по умолчанию 200 МиБ). |
| `edge-telemetry` | `telemetry`           | 8083  | `GET /metrics` — Docker-метрики контейнеров для Prometheus. Читает `/var/run/docker.sock` (только чтение). |
| `edge-webhook`   | `webhook`             | 8084  | `POST /api/project/{id}/hook`: определяет GitLab/GitHub по заголовкам, проверяет подпись и лимит, проксирует тело в существующий PHP-эндпоинт. |
| `edge-gateway`   | `nginx:1.27-alpine`   | 8080 → host `EDGE_GATEWAY_PORT` (по умолчанию 8090) | Канареечный шлюз: маршрутизирует только три потока в Go, всё остальное — в `web:8080`. |

Все Go-сервисы отдают `GET /healthz`. Отдельных host-портов у них по умолчанию
нет — доступ только из сети compose.

Конфигурация шлюза: [`.docker/nginx/edge.conf`](../.docker/nginx/edge.conf).

| Запрос через шлюз              | Куда уходит |
|--------------------------------|-------------|
| `POST /api/runner/saveSteps`   | `edge-progress:8081` |
| `POST /api/project/*/hook`     | `edge-webhook:8084` → PHP |
| `/media/...`                   | `edge-media:8082` → MinIO |
| всё остальное (в т.ч. `/api/doc`, `/metrics`) | `web:8080` (PHP) |

`/metrics` через шлюз **не** проксируется в телеметрию: запрос уходит в PHP и
возвращает его ответ. Метрики edge собираются Prometheus напрямую с
`edge-telemetry:8083`.

## 2. Переменные окружения

Все значения с безопасными дефолтами описаны в `.env.dist` (блок
`edge service (Go)`):

| Переменная | Значение по умолчанию | Комментарий |
|------------|-----------------------|-------------|
| `EDGE_GATEWAY_PORT` | `8090` | Host-порт канареечного шлюза. 8082 не меняется. |
| `EDGE_PROGRESS_PORT` | пусто | Прямой host-порт `edge-progress`; пусто = не публикуется. |
| `EDGE_MEDIA_PORT` | пусто | Прямой host-порт `edge-media`; пусто = не публикуется. |
| `EDGE_TELEMETRY_PORT` | пусто | Прямой host-порт `edge-telemetry` для отладки scrape. |
| `DOCKER_GID` | `999` (`${DOCKER_GID:-999}`) | GID группы `/var/run/docker.sock`. Узнать: `stat -c '%g' /var/run/docker.sock`. |
| `GITLAB_WEBHOOK_SECRET` | пусто | Пусто — проверка `X-Gitlab-Token` отключена. |
| `GITHUB_WEBHOOK_SECRET` | пусто | Пусто — проверка `X-Hub-Signature-256` отключена. |
| `WEBHOOK_RATE_LIMIT_PER_SECOND` | `100` | Лимит на проект/провайдер, `0` отключает. |
| `PHP_WEBHOOK_BASE_URL` | `http://web:8080` | Куда `edge-webhook` проксирует события. |
| `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY` | пусто | Если пусто, compose подставляет `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD`. |

Секреты не коммитить: боевые значения — только в `.env.local` или секретах
окружения.

## 3. Сборка и запуск

Локальный стенд (`docker-compose.yml`):

```sh
docker compose --profile edge up -d --build
```

Только edge-сервисы (остальной стек уже поднят):

```sh
docker compose --profile edge up -d --build \
  edge-progress edge-media edge-webhook edge-telemetry edge-gateway
```

MVP/прод-подобный стенд:

```sh
docker compose -f dc.mvp.yml --profile edge up -d --build
docker compose -f dc.unitman.yml --profile edge up -d --build
```

Проверить, что поднялись пять сервисов:

```sh
docker compose --profile edge ps
```

Без `--profile edge` образ `unitman-edge:dev` не собирается и не запускается.

Для `edge-telemetry` на большинстве хостов нужен реальный GID сокета:

```sh
stat -c '%g' /var/run/docker.sock   # например, 1001
printf 'DOCKER_GID=1001\n' >> .env  # или задайте в .env.local
```

## 4. Smoke через канареечный шлюз (8090)

`GATEWAY=http://localhost:${EDGE_GATEWAY_PORT:-8090}`.

1. PHP-фолбэк — запрос уходит в `web:8080`:

   ```sh
   curl -s -o /dev/null -w '%{http_code}\n' "$GATEWAY/api/doc"     # 200
   ```

2. Прогресс раннера — уходит в Go. Токен (`responseId`) должен быть
   зарегистрирован в Redis (как и для PHP-пути):

   ```sh
   tests/load/register-progress-token.sh smoke-edge-progress

   curl -s -X POST "$GATEWAY/api/runner/saveSteps" \
     -H 'Content-Type: application/json' \
     -d '{"responseId":"smoke-edge-progress",
          "stepsContent":"[{\"command\":\"echo ok\",\"response\":\"ok\",\"success\":true,\"unixtime\":1730000000}]",
          "finished":false,"success":true}'
   # true
   ```

3. Webhook GitLab — Go проверяет провайдера и проксирует в PHP; ответ приходит
   от PHP (не `502`):

   ```sh
   curl -s -i -X POST "$GATEWAY/api/project/1/hook" \
     -H 'Content-Type: application/json' \
     -H 'X-Gitlab-Event: Push Hook' \
     -H 'X-Gitlab-Token: smoke-token' \
     -d '{"object_kind":"push","project":{"id":1}}'
   ```

4. Медиа — потоковая загрузка/выгрузка:

   ```sh
   printf 'PK\003\004smoke-gateway-test-payload' > /tmp/gateway-test.zip

   curl -s -o /dev/null -w '%{http_code}\n' -X PUT \
     --data-binary @/tmp/gateway-test.zip \
     -H 'Content-Type: application/zip' \
     "$GATEWAY/media/smoke/gateway-test.zip"          # 200

   curl -s -o /tmp/gateway-test.out \
     "$GATEWAY/media/smoke/gateway-test.zip"          # 200
   cmp /tmp/gateway-test.zip /tmp/gateway-test.out    # байт-в-байт

   curl -s -o /dev/null -w '%{http_code}\n' -X DELETE \
     "$GATEWAY/media/smoke/gateway-test.zip"          # 204
   ```

5. Телеметрия: `GET $GATEWAY/metrics` **не** должен возвращать метрики edge
   (запрос уходит в PHP). Метрики снимаются напрямую из сети compose:

   ```sh
   docker compose exec -T edge-progress wget -qO- http://edge-telemetry:8083/metrics
   ```

   Либо включите прямой порт (см. ниже) и запросите
   `http://localhost:${EDGE_TELEMETRY_PORT}/metrics`.

### Прямой smoke на опциональных host-портах

Прямые порты выключены по умолчанию. Два способа их получить:

**A. Раскомментировать `ports:` у нужного сервиса** в `common-services.yml`
(блок `edge (Go) services`) и задать порт:

```sh
printf 'EDGE_PROGRESS_PORT=18081\nEDGE_MEDIA_PORT=18082\nEDGE_TELEMETRY_PORT=18083\n' >> .env
docker compose --profile edge up -d edge-progress edge-media edge-telemetry
curl -s http://localhost:18081/healthz    # {"status":"ok"}
curl -s http://localhost:18082/healthz    # {"status":"ok"}
```

**B. Разовый контейнер с публикацией порта (без правок compose):**

```sh
docker compose --profile edge run --rm -d --no-deps \
  --name edge-progress-direct -p 18081:8081 edge-progress
curl -s http://localhost:18081/healthz
docker rm -f edge-progress-direct
```

## 5. Перевод трафика на канарейку

### Runner `saveSteps`

Ничего менять в PHP не нужно: Go-сервис пишет в те же ключи Redis и
публикует в тот же канал Centrifugo. Достаточно указать раннеру новый URL:

```
POST http://<host>:8090/api/runner/saveSteps
```

Если задан `RUNNER_CALLBACK_SECRET`, заголовок `X-Runner-Signature` обязателен
и для Go-пути (алгоритм тот же: `hex(hmac_sha256(responseId . stepsContent, secret))`).
При пустом секрете проверка отключена — так проще раскатывать: сначала
бэкенд, потом раннер.

### Webhook GitLab/GitHub

В настройках проекта укажите URL канареечного шлюза:

```
http://<host>:8090/api/project/<projectId>/hook
```

- GitLab: Secret Token должен совпадать с `GITLAB_WEBHOOK_SECRET` (если задан).
- GitHub: Content type `application/json`, Secret = `GITHUB_WEBHOOK_SECRET`
  (если задан).

`edge-webhook` **не заменяет** event store: он валидирует запрос и проксирует
его в существующий PHP-эндпоинт, поэтому события, проекции и обработчики
остаются прежними. При недоступности PHP шлюз вернёт `502 webhook.php_unavailable`,
и провайдер сможет повторить доставку.

### Медиа

`edge-media` **дополняет** существующий путь presigned-загрузок в Mediateka:
presigned-ссылки продолжают работать, а `PUT/GET/DELETE /media/{key}` через
шлюз даёт потоковую передачу без буферизации больших архивов в PHP.

## 6. Prometheus

Задача `edge-telemetry` добавлена в [`.docker/prometheus/prometheus.yml`](../.docker/prometheus/prometheus.yml):

```yaml
- targets: ['node-exporter:9100', 'web:2112', 'web-worker:2113', 'web-temporal:9091', 'edge-telemetry:8083']
```

Существующие цели web/worker/temporal сохранены. Prometheus подхватит новую
цель после перезапуска (например, `docker compose -f dc.tools.yml restart prometheus`);
пока профиль `edge` выключен, цель будет `down` — это ожидаемо.

## 7. Откат

Откат не требует изменения трафика на PHP: шлюз публикуется на отдельном порту
(8090), а дефолтный API на 8082 всё это время обслуживается PHP-стеком.

```sh
# Остановить только edge-профиль, PHP-стек продолжает работать
docker compose --profile edge stop \
  edge-progress edge-media edge-webhook edge-telemetry edge-gateway

# или удалить контейнеры профиля (образ и данные не трогаются)
docker compose --profile edge rm -f \
  edge-progress edge-media edge-webhook edge-telemetry edge-gateway
```

После остановки:

- верните раннеру `POST http://<host>:8082/api/runner/saveSteps`;
- верните webhook'и на `http://<host>:8082/api/project/<projectId>/hook`;
- PHP-путь полностью функционален, event store и Mediateka не затронуты.

Полный откат ролей RoadRunner (если он тоже раскатывался) описан в
`common-services.yml`: `docker compose stop web web-worker web-temporal && docker compose --profile rr-single up -d web-single`.

## 8. Проверка конфигурации

```sh
docker compose config -q
docker compose --profile edge config -q
docker compose -f dc.mvp.yml config -q
docker compose -f dc.mvp.yml --profile edge config -q
docker compose -f dc.unitman.yml config -q
docker compose -f dc.unitman.yml --profile edge config -q

docker run --rm -v "$PWD/.docker/nginx/edge.conf:/etc/nginx/conf.d/default.conf:ro" \
  nginx:1.27-alpine nginx -t
```
