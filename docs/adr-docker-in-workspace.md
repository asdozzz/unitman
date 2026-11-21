# ADR: запуск Docker-контейнеров внутри workspace

- **Статус:** Proposed.
- **Дата:** 2026-11-21.
- **Область:** workspace-контейнер, вложенный Docker, сеть `unitman_default`,
  проксирование портов, безопасность и ресурсные лимиты.
- **Связанные документы:** [`docs/workspaces.md`](workspaces.md:1),
  [`plans/opencode-workspaces.md`](../plans/opencode-workspaces.md:433),
  [`plans/workspace-contracts.md`](../plans/workspace-contracts.md:1),
  [`plans/adr-memory-optimization.md`](../plans/adr-memory-optimization.md:1).

## 1. Контекст и проблема

Workspace — это контейнер `${UNITMAN_WORKSPACE_SLUG}.workspaces`, в котором
живут несколько проектов, OpenCode и multi-root VS Code. Внутри workspace
пользователю и агенту нужен **Docker**: чтобы поднимать dev-сервисы
(`docker compose up`), прогонять интеграционные тесты, собирать образы и
запускать временные контейнеры рядом с кодом.

Возникает вопрос: **как дать процессам внутри workspace доступ к Docker-демону,
не отдавая им управление Docker-демоном хоста**. От выбора зависит:

- безопасность хоста и соседних workspace;
- изоляция сетей и возможность публиковать порты;
- совместимость с текущей сетью `unitman_default` и nginx-проксированием
  `{port}.{unit}.{project}.{proxyHost}`;
- стоимость эксплуатации: привилегии, образы, тома, лимиты, обновления.

Проксирование портов самого workspace уже работает и **не является предметом
этого выбора**: см. раздел 6.

## 2. Текущее состояние

Факты, на которые опирается решение:

1. Workspace разворачивается Go-раннером из шаблона
   `temporal/activity/workspace/docker-dind/` (внешний репозиторий
   `unitman-runner`). Снимок шаблона и порядок запуска зафиксированы в
   [`plans/opencode-workspaces.md`](../plans/opencode-workspaces.md:433).
2. В `compose.dind.yaml` workspace уже запускается как **Docker-in-Docker**:
   `runtime: sysbox-runc`, образ `asdozzz/dind-workspace:latest`, том
   `/var/lib/docker`, healthcheck `docker info`, `container_name:
   ${UNITMAN_WORKSPACE_SLUG}.workspaces`. Тот же файл — внешний артефакт
   Go-раннера, в этом репозитории он не изменяется.
3. IDE-сайдкар использует `network_mode: service:workspace`, поэтому порт IDE
   публикуется в network namespace workspace, а не в отдельном.
4. Workspace подключён к сети `unitman_default`, в которой nginx находит
   контейнер по имени `<workspaceSlug>.workspaces`.
5. Проксирование портов workspace реализовано эндпоинтом
   `POST /api/opencode/workspace/ssylkaPoPortu`
   ([`WorkspaceController.php`](../src/OpenCode/Infra/Controller/WorkspaceController.php:291)),
   use case
   [`PoluchitSsylkuWorkspaceQuery.php`](../src/OpenCode/Business/UseCase/PoluchitSsylkuWorkspaceQuery.php:39)
   и read-model
   [`WorkspaceLinkHolder.php`](../src/OpenCode/Business/ReadModel/WorkspaceLinkHolder.php:10).
   Команда
   [`PoluchitSsylkuWorkspace.php`](../src/OpenCode/Business/Command/PoluchitSsylkuWorkspace.php:16)
   принимает `workspaceId`, `port`, `suffix`, `type`; проверяются владелец,
   статус `RUNNING` и диапазон порта `1–65535`.
6. Ссылка строится по формуле
   `{scheme}://{port}.{workspaceSlug}.workspaces.{proxyHost}[:{proxyPort}]`
   ([`SsylkaRedaktoraWorkspace.php`](../src/OpenCode/Business/Utils/SsylkaRedaktoraWorkspace.php:16),
   [`UmeetUpravlyatProektamiWorkspace.php`](../src/OpenCode/Business/Port/UmeetUpravlyatProektamiWorkspace.php:48)).
7. Для сравнения: контейнер `web` сам монтирует
   `/var/run/docker.sock` хоста, потому что управляет контейнерами юнитов
   ([`docker-compose.yml`](../docker-compose.yml:34),
   [`dc.unitman.yml`](../dc.unitman.yml:32), [`dc.mvp.yml`](../dc.mvp.yml:98)).
   `edge-telemetry` читает сокет **только на чтение**
   ([`common-services.yml`](../common-services.yml:177)). Это привилегированные
   сервисы платформы, а не пользовательский workspace.

## 3. Рассмотренные варианты

### 3.1. Проброс Docker-сокета хоста (`/var/run/docker.sock`)

**Схема.** В `compose.dind.yaml` workspace добавляется том:

```yaml
volumes:
  - /var/run/docker.sock:/var/run/docker.sock
```

Внутри workspace `DOCKER_HOST` указывает на `unix:///var/run/docker.sock`.
Команды `docker`, `docker compose` и клиенты Docker API работают с **демоном
хоста** напрямую.

**Плюсы.**

- Не нужен вложенный `dockerd`, sysbox или привилегированный контейнер.
- Образы и слои общие с хостом: быстрый старт, экономия диска.
- «Из коробки» работают `docker compose`, bind-mount путей хоста и все
  возможности демона хоста.
- Не требуется менять Go-раннер, кроме монтирования сокета.

**Минусы.**

- Любой процесс в workspace получает **фактически root на хосте**: может
  запустить привилегированный контейнер, смонтировать `/`, прочитать секреты
  других контейнеров, остановить платформу.
- Контейнеры, запущенные через сокет, живут на демоне хоста и **публикуют
  порты в network namespace хоста**, а не workspace. Nginx, который ходит на
  `http://<workspaceSlug>.workspaces:<port>`, их не увидит.
- Возможны коллизии имён, портов и томов с контейнерами юнитов и платформы.
- Нет барьера между несколькими workspace: один пользователь видит и может
  менять контейнеры другого.
- `docker compose` внутри workspace видит чужие compose-проекты хоста.

**Безопасность.** Вариант неприемлем для multi-tenant-среды: компрометация
workspace (в том числе через prompt injection в агенте) равна компрометации
хоста. Монтирование сокета в пользовательский контейнер несовместимо с
изоляцией.

**Сеть и проксирование.** Прямо несовместим с текущей схемой: порты вложенных
контейнеров оказываются на хосте, а не на workspace. Чтобы вернуть
доступность, нужны ручные пробросы, `network_mode: container:<workspace>` или
публикация на хосте и отдельный proxy-маршрут — это усложняет и ломает
модель `{port}.{workspaceSlug}.workspaces`.

**Вывод.** Не рекомендуется.

### 3.2. Docker-in-Docker (dind)

Есть два подвида: отдельный dind-сайдкар и `dockerd` внутри самого workspace.

#### 3.2.1. dind-сайдкар

**Схема.** Рядом с workspace поднимается сервис `dind` на образе
`docker:dind` (обычно `privileged: true`). Workspace общается с ним через
`DOCKER_HOST=tcp://dind:2375` (лучше — TLS на `2376`) или через общий
Unix-сокет в томе.

```yaml
services:
  workspace:
    environment:
      DOCKER_HOST: tcp://dind:2375
    depends_on: [dind]
  dind:
    image: docker:dind
    privileged: true
    volumes:
      - workspace_docker:/var/lib/docker
```

**Плюсы.**

- Docker-демон изолирован от демона хоста: нет доступа к чужим контейнерам
  через host socket.
- Workspace-образ не обязан содержать `dockerd`.
- Данные вложенного Docker можно вынести в отдельный том и переживать
  перезапуск workspace.
- Можно использовать TLS и ограничить API по сети.

**Минусы.**

- `privileged: true` даёт контейнеру почти все возможности хоста; это широкая
  поверхность атаки на ядро.
- Нужны TLS-сертификаты или осознанный отказ от них; открытый `2375` внутри
  сети — риск.
- Отдельный network namespace: порты вложенных контейнеров публикуются в
  namespace сайдкара, а не workspace. Nginx снова не увидит их по адресу
  workspace.
- Дополнительный контейнер, том, healthcheck и жизненный цикл; сложнее
  диагностика.
- Ресурсы сайдкара нужно лимитировать отдельно.

**Сеть и проксирование.** Работает с текущим проксированием только если
сайдкар запущен с `network_mode: service:workspace` (тогда публикация портов
попадает в namespace workspace, а API доступен на `127.0.0.1`) либо если перед
ним стоит собственный TCP-форвардер. В противном случае порты вложенных
сервисов не видны через `{port}.{workspaceSlug}.workspaces`.

**Безопасность.** Лучше host socket, но `privileged` dind — это по-прежнему
высокий риск эскалации через ядро и устройство. Для недоверенного кода нужен
sysbox, rootless или отдельная VM.

#### 3.2.2. dind внутри workspace (sysbox-runc)

**Схема.** `dockerd` запускается внутри workspace-контейнера. Workspace
создаётся с `runtime: sysbox-runc`; sysbox даёт вложенному Docker
user-namespace-изоляцию и не требует `privileged: true`. Именно это уже
описано в `compose.dind.yaml` и
[`plans/opencode-workspaces.md`](../plans/opencode-workspaces.md:464):

```yaml
services:
  workspace:
    container_name: ${UNITMAN_WORKSPACE_SLUG}.workspaces
    runtime: sysbox-runc
    image: asdozzz/dind-workspace:latest
    mem_limit: ${UNITMAN_MEMORY_LIMIT:-3g}
    memswap_limit: ${UNITMAN_MEMORY_LIMIT:-3g}
    volumes:
      - ${UNITMAN_WORKSPACE_APP_VOLUME}:/workspace
      - ${UNITMAN_WORKSPACE_DOCKER_VOLUME}:/var/lib/docker
      - ${UNITMAN_WORKSPACE_OPENCODE_VOLUME}:/root/.local/share/opencode
      - ./seed:/seed:ro
```

**Плюсы.**

- Host Docker socket не монтируется: workspace не управляет контейнерами
  платформы.
- Вложенный `dockerd` живёт в network namespace workspace, поэтому
  `docker run -p 3000:3000` публикует порт **на workspace-контейнере**;
  nginx-прокси `http://<workspaceSlug>.workspaces:3000` видит сервис без
  дополнительных форвардеров.
- `docker compose`, `docker build`, тома и сети работают привычно для
  пользователя.
- Данные `/var/lib/docker` вынесены в том и переживают архив/рестарт.
- sysbox снижает набор привилегий по сравнению с `privileged` dind.

**Минусы.**

- Требуется установленный на хосте `sysbox-runc` и совместимое ядро; это
  внешняя зависимость Go-раннера и инфраструктуры.
- Нужен специальный образ `asdozzz/dind-workspace:latest` с `dockerd`,
  entrypoint и healthcheck.
- Вложенный Docker потребляет заметно больше ресурсов: демон, слои, кэш
  сборки, тома. Нужны лимиты памяти, CPU, pids и диска.
- Обновления sysbox и образа — часть поверхности безопасности; уязвимости
  ядра и sysbox критичны.
- Диагностика вложенного Docker сложнее, чем хостового.

**Безопасность.** Компромисс: без host socket и без `privileged`. Sysbox
использует user namespaces и ограничивает capabilities, но не является
границей безопасности уровня VM. Для недоверенного кода остаются риски
resource exhaustion, атак на ядро и утечки данных через общие тома/сеть.

### 3.3. Rootless Docker / rootless-подход

**Схема.** Внутри workspace `dockerd` запускается от непривилегированного
пользователя через `dockerd-rootless.sh` (rootlesskit + slirp4netns). Демон
использует user namespaces, `fuse-overlayfs` и rootless-сеть. Workspace может
быть обычным контейнером без `privileged` и без sysbox (при поддержке
user namespaces на хосте).

**Плюсы.**

- Наименьшие привилегии демона: нет root на хосте, нет доступа к устройствам.
- Нет host socket и не нужен `privileged` dind.
- Хорошо сочетается с политикой «недоверенный код в workspace».
- Вложенные порты публикуются в namespace workspace через rootlesskit/slirp,
  поэтому проксирование потенциально сохраняется.

**Минусы.**

- Сложная настройка: `newuidmap`/`newgidmap`, `/etc/subuid`, `/etc/subgid`,
  cgroup v2, `fuse-overlayfs`, `slirp4netns`.
- Производительность сети и файловой системы ниже, чем у обычного dind.
- Порты ниже `1024` недоступны без дополнительных ухищрений; часть
  `docker compose`-сценариев и `network_mode` работает иначе.
- Не все образы и volume-драйверы совместимы.
- Вложенность «rootless внутри sysbox/контейнера» требует отдельной проверки;
  не все комбинации ядра и runc поддерживаются.

**Безопасность.** Лучший вариант по минимизации привилегий, но поверхность
атаки смещается в rootlesskit, slirp4netns и user namespaces ядра. Нужны
актуальные версии и лимиты ресурсов.

**Сеть и проксирование.** Корректная публикация портов возможна, но требует
проверки: rootlesskit должен слушать `0.0.0.0` в namespace workspace, а не
только loopback. Для нестандартных сетей и `network_mode` поведение может
отличаться от обычного dind.

## 4. Сравнение вариантов

| Критерий | Host socket | dind-сайдкар | dind в workspace (sysbox) | Rootless |
|---|---|---|---|---|
| Доступ к Docker хоста | полный | нет | нет | нет |
| Привилегии | сокет = root на хосте | обычно `privileged` | sysbox, без `privileged` | user namespaces |
| Изоляция от других workspace | нет | средняя | средне-высокая | высокая |
| Публикация портов в namespace workspace | нет | только при общем netns | да | да, с ограничениями |
| Совместимость с `{port}.{slug}.workspaces` | требует обходов | требует общего netns/форвардера | нативная | требует проверки |
| Совместимость с `unitman_default` | контейнеры на хосте | контейнеры в netns сайдкара | контейнеры в netns workspace | аналогично |
| Сложность эксплуатации | низкая | средняя | средне-высокая | высокая |
| Ресурсные лимиты | не контролируются workspace | отдельно для сайдкара | `mem_limit`, pids, диск | аналогично |
| Пригодность для недоверенного кода | нет | ограниченно | ограниченно | лучше всего |

## 5. Риски безопасности (общие)

1. **Эскалация до хоста.** Host socket даёт прямой root-эквивалент.
   `privileged` dind и уязвимости sysbox/ядра — путь к escape. Нужны
   обновления, минимальные capabilities и запрет `--privileged` внутри
   workspace.
2. **Доступ к внутренним сервисам.** Workspace подключён к
   `unitman_default`, поэтому из него видны `web`, `postgres`, `redis`,
   `minio`, `temporal` и другие сервисы. Это не зависит от способа
   docker-in-workspace. Нужны сетевые политики, отдельная сеть для
   workspace или egress-фильтрация; не хранить в workspace лишние секреты.
3. **Компрометация агента.** Prompt injection и вредоносный код проекта
   действуют с правами workspace. Docker внутри расширяет возможности атаки:
   запуск майнера, сканирование сети, попытки escape.
4. **Resource exhaustion.** Вложенный Docker может занять диск
   (`/var/lib/docker`), память, pids и CPU. Нужны квоты на том, `mem_limit`,
   `pids_limit`, `cpu` и очистка неиспользуемых образов/томов.
5. **Supply chain.** `docker pull` и сборка образов внутри workspace тянут
   недоверенный код. Нужны allowlist реестров, запрет `--privileged` и
   аудит.
6. **Соседство workspace.** Общие тома, сеть `unitman_default` и имена
   контейнеров создают риск случайного или намеренного влияния на соседей.
7. **Секреты.** Токены клонирования и переменные проектов не должны попадать
   во вложенные контейнеры и логи; для git уже действует режим `readOnly` и
   `pre-push`-хук ([`docs/workspaces.md`](workspaces.md:232)).

## 6. Влияние на сеть и проксирование

### 6.1. Проксирование портов workspace не зависит от docker-in-workspace

Это **ключевой вывод ADR**. Nginx-конфигурация (внешний файл, не входит в
репозиторий) разбирает хост регулярным выражением:

```text
^(?<port>\d+)\.(?<unit>[^.]+)\.(?<project>[^.]+)\.(.+)$
  -> http://$unit.$project:$port
```

Для workspace `unit = <workspaceSlug>`, `project = workspaces`, поэтому
запрос `https://<port>.<workspaceSlug>.workspaces.<proxyHost>` уходит на
`http://<workspaceSlug>.workspaces:<port>` в сети `unitman_default`.

Этот маршрут работает **уже сейчас** и не меняется при выборе host socket,
dind или rootless. Эндпоинт
`POST /api/opencode/workspace/ssylkaPoPortu` только строит ссылку по формуле
`{scheme}://{port}.{workspaceSlug}.workspaces.{proxyHost}`; он не знает и не
должен знать, как внутри workspace запущен Docker. Формула совпадает с
общим правилом проекта
([`docs/unitman-config.md`](unitman-config.md:223),
[`plans/helper-type-protocol.md`](../plans/helper-type-protocol.md:25)).

### 6.2. Когда внутренние контейнеры становятся доступны через тот же хост

Внутренний контейнер доступен через
`{port}.{workspaceSlug}.workspaces.{proxyHost}`, если одновременно выполнены
условия:

1. контейнер запущен **в network namespace workspace** (dind внутри
   workspace или сайдкар с `network_mode: service:workspace`);
2. порт опубликован (`-p`/`ports:`), а не просто открыт внутри внутренней
   bridge-сети;
3. публикация слушает `0.0.0.0`, а не только `127.0.0.1`;
4. порт не занят другим процессом в namespace workspace;
5. nginx может достучаться до `<workspaceSlug>.workspaces` по
   `unitman_default`.

При host socket порты публикуются на хосте, поэтому условие 1 не выполнено:
ссылка `{port}.{workspaceSlug}.workspaces` на такой сервис не ведёт. При
отдельном dind-сайдкаре без общего netns — то же самое.

### 6.3. Ограничения сети

- Внутренние Docker-сети workspace изолированы от `unitman_default`. Имена
  `postgres`, `redis`, `web` не резолвятся во вложенных контейнерах
  автоматически: вложенный демон имеет собственный DNS и собственные bridge-
  сети.
- Если вложенному контейнеру нужен доступ к сервисам платформы, это нужно
  делать явно: через workspace как прокси/форвардер, через публикацию порта
  на workspace, через `dns`/`extra_hosts` или через сеть, которую видит
  вложенный демон. Автоматического наследования `unitman_default` нет.
- Workspace сам находится в `unitman_default` и видит платформенные сервисы.
  Это отдельный риск, не связанный с docker-in-workspace.
- Порт IDE (`47000`) и порты внутренних сервисов не должны конфликтовать;
  диапазон публикуемых портов стоит ограничить и валидировать.

## 7. Решение (рекомендация)

**Рекомендуется Docker-in-Docker внутри workspace с `runtime: sysbox-runc`**
(вариант 3.2.2), то есть сохранить и закрепить текущий подход
`compose.dind.yaml`:

- не монтировать `/var/run/docker.sock` хоста в workspace;
- не запускать отдельный `privileged` dind-сайдкар без необходимости;
- запускать `dockerd` внутри workspace-контейнера под sysbox;
- хранить `/var/lib/docker` в отдельном именованном томе;
- публиковать порты внутренних сервисов в namespace workspace через
  `-p 0.0.0.0:<port>:<port>` и использовать существующее проксирование
  `{port}.{workspaceSlug}.workspaces`;
- ограничивать ресурсы workspace (`mem_limit`, `memswap_limit`, pids, диск) и
  очищать неиспользуемые образы/тома;
- не давать workspace лишних сетевых прав: рассмотреть выделенную сеть или
  egress-политику вместо прямого доступа ко всем сервисам `unitman_default`.

**Условия включения:**

1. на всех хостах Go-раннера установлен и обновляется `sysbox-runc`;
2. образ `asdozzz/dind-workspace:latest` содержит `dockerd`, entrypoint и
   healthcheck `docker info`;
3. для workspace выделены том под `/var/lib/docker` и лимиты ресурсов;
4. Go-раннер не добавляет workspace в сеть с избыточными правами без
   сетевой политики;
5. для недоверенных пользователей рассматривается rootless-режим как
   дополнительное ужесточение (отдельная задача).

**Rootless** рекомендуется как направление усиления безопасности, но не как
немедленная замена: сначала нужны проверка совместимости с sysbox, замер
производительности и тесты `docker compose`/портов.

**Host socket** отклоняется для workspace.

## 8. Последствия

- Пользовательский workspace получает полноценный `docker`/`docker compose`
  без доступа к Docker-демону хоста.
- Проксирование портов workspace остаётся без изменений; для внутренних
  сервисов действует то же правило при выполнении условий раздела 6.2.
- Появляются эксплуатационные требования: sysbox, образ с `dockerd`,
  том `/var/lib/docker`, лимиты и очистка.
- Увеличивается потребление ресурсов на workspace; это нужно учитывать в
  планировании ёмкости.
- Требуется отдельная работа по сетевой изоляции workspace от
  `unitman_default`.

## 9. Что остаётся за рамками

- Изменения `compose.dind.yaml`, Dockerfile и Go-кода `unitman-runner` — это
  внешний репозиторий; в рамках ADR код не меняется.
- Изменения PHP-кода, эндпоинтов, OpenAPI и конфигов nginx.
- Установка и обновление `sysbox-runc` на хостах.
- Реализация UI для управления портами и вложенными compose-стеками.
- Сетевые политики и egress-фильтрация для `unitman_default`.
- Полноценный rootless-режим и его бенчмарки.

## 10. Открытые вопросы и следующие шаги

1. Нужен ли docker-in-workspace **всем** workspace или это opt-in на уровне
   проекта/пользователя? Как это отражается в UI и правах?
2. Как ограничивать диапазон публикуемых портов и обнаруживать конфликты?
   Нужен ли автоматический список портов через Docker events?
3. Как изолировать workspace от `postgres`/`redis`/`minio` в
   `unitman_default`: отдельная сеть, firewall, mTLS или прокси?
4. Какие лимиты считать целевыми: память, CPU, pids, размер
   `/var/lib/docker`? Как их считать и показывать?
5. Как очищать вложенные образы, тома и сети при архиве/удалении workspace?
   Сейчас cleanup раннера чистит только хостовый Docker.
6. Нужен ли rootless-режим и совместим ли он с sysbox на текущих ядрах?
7. Как защититься от `--privileged`, `--pid=host`, `--cap-add` и монтирования
   хостовых путей внутри вложенного Docker? Нужен ли allowlist возможностей?
8. Как обновлять sysbox и образ workspace без простоя и без регрессий
   безопасности?

## 11. Ссылки

- [`docs/workspaces.md`](workspaces.md:1) — жизненный цикл workspace, тома,
  ссылка на IDE.
- [`plans/opencode-workspaces.md`](../plans/opencode-workspaces.md:433) —
  Go-реализация, `compose.dind.yaml`, образ workspace.
- [`plans/workspace-contracts.md`](../plans/workspace-contracts.md:1) —
  контракты workspace.
- [`src/OpenCode/Infra/Controller/WorkspaceController.php`](../src/OpenCode/Infra/Controller/WorkspaceController.php:291)
  — эндпоинт `ssylkaPoPortu`.
- [`src/OpenCode/Business/UseCase/PoluchitSsylkuWorkspaceQuery.php`](../src/OpenCode/Business/UseCase/PoluchitSsylkuWorkspaceQuery.php:39)
  — валидации и построение ссылки.
- [`src/OpenCode/Business/ReadModel/WorkspaceLinkHolder.php`](../src/OpenCode/Business/ReadModel/WorkspaceLinkHolder.php:10)
  — read-model ссылки.
- [`src/OpenCode/Business/Command/PoluchitSsylkuWorkspace.php`](../src/OpenCode/Business/Command/PoluchitSsylkuWorkspace.php:16)
  — входная команда.
- [`src/OpenCode/Business/Utils/SsylkaRedaktoraWorkspace.php`](../src/OpenCode/Business/Utils/SsylkaRedaktoraWorkspace.php:16)
  — формула ссылки workspace.
- [`docs/unitman-config.md`](unitman-config.md:223) — общее правило ссылок на
  порты.
- [`docker-compose.yml`](../docker-compose.yml:34),
  [`dc.unitman.yml`](../dc.unitman.yml:32),
  [`dc.mvp.yml`](../dc.mvp.yml:98) — примеры монтирования host socket в
  сервисах платформы.
- [`common-services.yml`](../common-services.yml:177) — `edge-telemetry` с
  read-only доступом к сокету.
- Nginx-конфигурация с regex
  `^(?<port>\d+)\.(?<unit>[^.]+)\.(?<project>[^.]+)\.(.+)$` — внешний файл
  инфраструктуры, в репозитории отсутствует.
- `temporal/activity/workspace/docker-dind/compose.dind.yaml` — внешний
  репозиторий `unitman-runner`.
