# Локальный реестр образов (unitman-registry)

## Назначение и размещение

`unitman-registry` — это Docker Registry v2 (образ `registry:2.8.3`) с
pull-through-прокси Docker Hub. Реестр разворачивается **в инфраструктуре
backend** и описан в [`common-services.yml`](../common-services.yml) /
[`docker-compose.yml`](../docker-compose.yml). Он больше не поставляется
runner-репозиторием: инфраструктура реестра перенесена в backend.

Сервис доступен:

- внутри сети `unitman_default` по имени `unitman-registry:5000`;
- на хосте — по опубликованному порту `5000` (`0.0.0.0:5000`).

## Публичный адрес и регистрация раннера

Backend возвращает раннеру адрес реестра в ответе `POST /api/runner/register`.
Источник значения — переменная окружения `UNITMAN_REGISTRY_PUBLIC_ADDRESS`
(см. `.env.dist`, DI-биндинг в `src/Runner/di.php`). Backend не занимается
раздачей этого адреса ниоткуда, кроме названной переменной.

Раннер обязан конфигурироваться адресом реестра **во время регистрации**
(`runner init`), а не хардкодом:

- адрес попадает в dind-entrypoint (`--insecure-registry`/mirror) и в
  `UNITMAN_CACHE_REPO`;
- строки вида `unitman-registry:5000` в коде раннера, конфигах или скриптах
  недопустимы — единственный источник адреса это ответ регистрации.

Пример значения для локального стека:

```dotenv
UNITMAN_REGISTRY_PUBLIC_ADDRESS=localhost:5000
```

Пример для внешних раннеров (за reverse-proxy/L4):

```dotenv
UNITMAN_REGISTRY_PUBLIC_ADDRESS=registry.example.com:5000
```

Адрес должен быть доступен с хоста раннера (firewall, DNS, TLS/reverse-proxy
решаются на уровне инфраструктуры backend — см. раздел «Эксплуатация»).

## Pull-through-прокси Docker Hub

Конфигурация — [`.docker/registry/simple.yml`](../.docker/registry/simple.yml),
монтируется в контейнер как `/etc/docker/registry/config.yml`.

- `proxy.remoteurl: https://registry-1.docker.io` — при `docker pull` образа,
  которого нет в локальном хранилище, реестр сам забирает его из Docker Hub,
  сохраняет слои локально и отдаёт клиенту.
- `proxy.ttl: 168h` — срок жизни кешированных манифестов (7 суток).
- `storage.cache.blobdescriptor: inmemory` — кеш дескрипторов blob'ов в памяти.
- `storage.delete.enabled: true` — разрешено удаление манифестов/слоёв через API.
- Хранилище — файловая система в `/var/lib/registry`, смонтированная из
  именованного тома `unitman-registry-data`.

Благодаря прокси раннерам не нужен прямой доступ к Docker Hub: достаточно
обратиться к `unitman-registry:5000/<образ>`. При этом Docker Hub видит запросы
с IP/аккаунта backend-хоста.

## Запуск и health-check

```bash
# создать/запустить только реестр, не трогая остальные сервисы
docker compose -f docker-compose.yml up -d unitman-registry

# статус и health
docker compose -f docker-compose.yml ps unitman-registry

# проверка API реестра (ожидается HTTP 200 и пустой JSON {})
curl -fsS http://localhost:5000/v2/

# альтернатива внутри контейнера
docker exec unitman-unitman-registry-1 \
  wget -q -O- http://127.0.0.1:5000/v2/
```

Healthcheck сервиса использует `wget -q -O /dev/null http://127.0.0.1:5000/v2/`
(`interval: 30s`, `timeout: 5s`, `retries: 5`, `start_period: 10s`), поэтому
`docker compose ps` показывает `healthy`.

Остановка:

```bash
docker compose -f docker-compose.yml stop unitman-registry
```

## Том и резервное копирование

Данные реестра хранятся в именованном томе Docker (проект `unitman`, каталог
`/home/leo/www/unitman`):

- том: `unitman_unitman-registry-data`;
- точка монтирования: `/var/lib/registry`.

Резервная копия тома:

```bash
docker run --rm \
  -v unitman_unitman-registry-data:/data:ro \
  -v "$PWD/var/backup:/backup" \
  alpine:3 sh -c 'tar czf /backup/unitman-registry-$(date +%F).tgz -C /data .'
```

Восстановление — в обратном порядке, при остановленном контейнере реестра:

```bash
docker compose -f docker-compose.yml stop unitman-registry
docker run --rm \
  -v unitman_unitman-registry-data:/data \
  -v "$PWD/var/backup:/backup:ro" \
  alpine:3 sh -c 'rm -rf /data/* && tar xzf /backup/unitman-registry-YYYY-MM-DD.tgz -C /data'
docker compose -f docker-compose.yml start unitman-registry
```

Кеш Docker Hub можно считать восстановимым: при потере тома образы подтянутся
заново при следующем pull. Тем не менее, если в реестр пушились собственные
образы, том обязателен к резервированию.

## Переезд со старого runner-реестра

Исторически реестр жил в runner-репозитории (`register_data/`, bind-mount в
`/var/lib/registry`) и слушал тот же порт `5000`. При переезде:

1. контейнер старого реестра останавливается (`docker compose stop unitman-registry`
   в каталоге runner-репозитория);
2. содержимое `register_data/` копируется в том `unitman_unitman-registry-data`;
3. запускается `unitman-registry` из backend;
4. проверяется `curl -fsS http://localhost:5000/v2/`.

Формат filesystem-хранилища Registry v2 совместим между образами, поэтому
существующие слои остаются доступными.

## Эксплуатация

- Порт `5000` публикуется на всех интерфейсах хоста. Для внешних раннеров
  публичный адрес (`UNITMAN_REGISTRY_PUBLIC_ADDRESS`) должен резолвиться и быть
  открыт в firewall; при выходе за пределы хоста рекомендуется TLS/reverse-proxy
  и отдельный DNS-имя.
- `X-Content-Type-Options: nosniff` включён в конфиге реестра.
- Логи смотрите через `docker compose logs -f unitman-registry`.
- Если registry используется за TLS-терминатором, убедитесь, что раннер
  настроен на корректную схему (`https://`), иначе pull-through будет
  отклоняться.
