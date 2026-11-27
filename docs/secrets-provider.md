# WS-9: SecretProvider и fail-closed ключевой материал

Абстракция доступа к секретам и ключам. Документ не содержит значений
секретов — только имена переменных и плейсхолдеры. Операционные шаги
генерации/ротации для релизного стека не дублируются: см.
[`unitman-release/docs/secrets.md`](../../unitman-release/docs/secrets.md).

## Зачем

До WS-9 ключи читались напрямую из `%env(...)%` в адаптерах, а PHP
`RunnerSecretGenerator` при пустом `RUNNER_SECRET_MASTER_KEY` молча брал
`APP_SECRET`. Это связывало идентичность раннеров с application secret и
не давало единой точки проверки ключей. Теперь:

- ключи приходят только через `App\Secrets\Business\Port\SecretProvider`;
- логические назначения ключей — через
  `App\Secrets\Business\Port\EncryptionKeyProvider`;
- отсутствие/неверный формат — доменное исключение, без дефолтов;
- есть health-команда и единый контракт для env/file/vault/kms.

Ключи не хранятся в БД и не задаются в коде. Допустимые источники —
переменные окружения, файлы с режимом 0600, Vault (KV v2). KMS-адаптер
является stub-ом и не имитирует успех.

## Модуль и регистрация

`src/Secrets` — обычный модуль modular monolith. Регистрация не требует
правок `Kernel.php`: [`src/Kernel.php`](../src/Kernel.php) импортирует
`./**/{di}.php`, поэтому достаточно
[`src/Secrets/di.php`](../src/Secrets/di.php). Границы модуля описаны в
[`deptrac.yaml`](../deptrac.yaml): `Secrets` — лист графа, `Unitman` и
`Runner` могут зависеть от него.

## Контракт `SecretProvider`

```php
interface SecretProvider
{
    public function get(string $name): string;   // отсутствует → SecretNotFound
    public function has(string $name): bool;     // небезопасная конфигурация → исключение
    public function rotate(string $name): void;  // не умеет → UnsupportedSecretOperation
}
```

Сообщения исключений содержат только имя переменной/файла, но никогда —
значение. `rotate()` либо выполняет ротацию, либо честно падает: успех не
имитируется.

| Бэкенд | `get` | `has` | `rotate` | Ошибки |
|---|---|---|---|---|
| `env` (default) | переменная окружения по имени; пусто → `SecretNotFound` | `false` для пустой/отсутствующей | `UnsupportedSecretOperation` | имя переменной в сообщении |
| `file` | `$SECRETS_DIR/<NAME>`; имя `^[A-Z0-9_]+$`; режим 0600 | `false` только для отсутствующего файла; 0644/0640 → `InsecureSecretFile` | `UnsupportedSecretOperation` | `InvalidSecretName`, `InsecureSecretFile`, `SecretNotFound` |
| `vault` | KV v2 GET `${VAULT_ADDR}/v1/secret/data/<NAME>` c `X-Vault-Token`; 404 → `SecretNotFound` | тот же read | `UnsupportedSecretOperation` | пустые `VAULT_ADDR`/`VAULT_TOKEN` → `SecretProviderNotConfigured`, сетевых вызовов нет |
| `kms` | `UnsupportedSecretOperation` | `UnsupportedSecretOperation` | `UnsupportedSecretOperation` | пустой `KMS_KEY_ID` → `SecretProviderNotConfigured`; успех не имитируется |

Выбор бэкенда — `SECRET_PROVIDER`; неизвестное значение — ошибка
конфигурации при создании сервиса (fail-fast до первого секрета).

## Переменные окружения

Все значения — плейсхолдеры, боевые задаются только секретами окружения.

| Переменная | Назначение |
|---|---|
| `SECRET_PROVIDER` | `env` (по умолчанию), `file`, `vault`, `kms` |
| `SECRETS_DIR` | каталог для `file`-бэкенда; файлы строго 0600 |
| `VAULT_ADDR` | адрес Vault KV v2 для `vault`-бэкенда |
| `VAULT_TOKEN` | токен Vault (никогда не коммитится) |
| `KMS_KEY_ID` | id ключа для `kms`-stub |
| `OPENCODE_CREDENTIALS_KEY` | AES-256-GCM, base64 32 байта: реквизиты OpenCode |
| `LICHNIY_TOKEN_CREDENTIALS_KEY` | AES-256-GCM, base64 32 байта: repo- и personal-токены |
| `MEDIATEKA_UPLOAD_TOKEN_SECRET` | HMAC-ключ upload-токенов |
| `RUNNER_SECRET_MASTER_KEY` | HMAC-SHA256 корень секретов раннеров |

## Назначения ключей

`EncryptionKeyProvider` сопоставляет логические назначения с физическими
именами:

| Назначение (`KeyPurpose`) | Физическое имя | Тип |
|---|---|---|
| `opencode_credentials` | `OPENCODE_CREDENTIALS_KEY` | AES-256-GCM, base64 32 байта |
| `repo_tokens` | `LICHNIY_TOKEN_CREDENTIALS_KEY` | AES-256-GCM, base64 32 байта |
| `personal_tokens` | `LICHNIY_TOKEN_CREDENTIALS_KEY` | AES-256-GCM, base64 32 байта |
| `upload_tokens` | `MEDIATEKA_UPLOAD_TOKEN_SECRET` | непустой HMAC-ключ |
| `unit_variables` | `RUNNER_SECRET_MASTER_KEY` | per-runner HKDF → AES-256-GCM |
| `runner_secrets` | `RUNNER_SECRET_MASTER_KEY` | непустой HMAC-ключ |

Валидация строгая: base64 декодируется в ровно 32 байта для AES-256;
усечение/паддинг не выполняются. Для HMAC пустая/пробельная строка —
ошибка. Сообщения содержат только имя переменной.

`PersonalTokenOpenSslShifr` (WS-2 repo-токены и personal-токены) получает
ключ через назначение `repo_tokens`, но формат остаётся прежним:
`base64(nonce[12] || tag[16] || ciphertext)`, AAD = `repoId` для
репозиториев и `accountId:tokenId` для личных токенов. Существующие
шифротексты расшифровываются без миграции.

`RunnerSecretGenerator` (PHP) получает ключ только из
`runner_secrets`. **Fallback на `APP_SECRET` удалён**, включая dev/local.
При том же master key деривация не изменилась:
`hash_hmac('sha256', runnerId, masterKey)`, поэтому уже выданные секреты
раннеров продолжают проверяться.

`UnitVariableCipher` использует тот же master key: из per-runner секрета через
HKDF (`unitman-unit-variables-v1`) выводится AES-256-GCM ключ для hidden-
переменных юнита. В Temporal payload попадает только `ValueCipher`; plaintext
появляется лишь в памяти раннера.

## Health-check

```bash
php bin/console app:secrets:health
```

- проверяет четыре физических имени для выбранного бэкенда
  (`OPENCODE_CREDENTIALS_KEY`, `LICHNIY_TOKEN_CREDENTIALS_KEY`,
  `MEDIATEKA_UPLOAD_TOKEN_SECRET`, `RUNNER_SECRET_MASTER_KEY`);
- печатает только `MISSING: NAME` / `INVALID: NAME` и итоговый статус;
- exit code `0` — всё есть и форматы валидны, `1` — есть пропуски/ошибки;
- значения никогда не выводятся и не логируются.

## Ротация

Порядок для ключей, которые шифруют данные (repo/personal токены):

1. Сгенерировать новый ключ: `openssl rand -base64 32` (AES) или
   `openssl rand -hex 32` (HMAC) — см. release-документ.
2. Разложить новое значение в выбранный бэкенд (env/файл 0600/Vault),
   сохранив старое значение доступным для rollback.
3. Перезапустить PHP workers/контейнеры. Проверить
   `php bin/console app:secrets:health`.
4. Для repo/personal токенов online re-encrypt не реализован: после смены
   ключа токены нужно добавить заново через UI (репозитории и личные токены).
   Legacy-команда `app:security:encrypt-repo-tokens` удалена вместе с
   upcaster'ом; БД создаётся с нуля по актуальным миграциям.
5. Проверить чтение репозиториев/личных токенов и снять старое значение
   только после успешной проверки.

Для HMAC-ключей (`runner_secrets`, `upload_tokens`) шаг 4 не нужен:
ротация инвалидирует старые подписи. Раннеры потребуют повторной
инициализации (`runner init`), незавершённые upload-токены нужно повторить.

Ротация выполняется по одному секрету: сначала deploy нового значения,
затем проверка, затем вывод старого из эксплуатации.

## Провайдеры: плюсы и минусы

| Критерий | `env` | `file` | `vault` |
|---|---|---|---|
| Инфраструктура | не нужна | только каталог/volume | отдельный Vault, HA, unseal |
| Где ключ | переменные процесса | файл `0600` | централизованно |
| Риск утечки | `docker inspect`, `/proc`, дампы, дочерние процессы | доступ к ФС, бэкапы, снапшоты, ошибочный `COPY` в образ | утечка Vault-токена/политик |
| Ротация | рестарт процесса | новый файл + рестарт | централизованно, но адаптер read-only |
| Аудит | нет | нет | audit log/ACL |
| Зависимость | нет | нет | сеть, доступность, auth |
| Когда выбирать | dev/test, single-tenant | single-host prod, Docker/K8s secrets | multi-service prod, compliance |

Рекомендация: dev/test — `env`; текущий release — `file`; при росте и
требованиях аудита — `vault`. KMS не является хранилищем секретов: это
отдельный envelope-сценарий, текущий адаптер — fail-closed stub.

## Blast radius и риски

- Потеря `LICHNIY_TOKEN_CREDENTIALS_KEY` = необратимая потеря
  repo/personal токенов: ciphertext без ключа не восстановить. Резервная
  копия ключа обязательна.
- Смена `RUNNER_SECRET_MASTER_KEY` инвалидирует все per-runner секреты:
  каждый раннер должен пройти `runner init` заново. Этот же ключ через HKDF
  выводит per-runner AES-ключ для hidden-переменных юнита; незавершённые
  workflow с такими payload'ами нужно перезапустить.
- Смена `MEDIATEKA_UPLOAD_TOKEN_SECRET` инвалидирует незавершённые
  upload-токены; JWT/логины не затрагиваются.
- `APP_SECRET` больше не влияет на секреты раннеров и upload-токены.
- Порядок «сначала удалить старый ключ, потом перешифровать» приводит к
  нечитаемым данным. Сначала новый ключ + перешифровка, потом удаление.

## Threat model

- Защищаемся от: случайного коммита секретов, чтения ключей из БД/кода,
  path traversal в файловом бэкенде, world-readable файлов, тихого
  использования дефолтного/пустого ключа, подмены бэкенда неизвестным
  значением.
- Не защищаемся от: компрометации хоста/процесса с доступом к env или
  `SECRETS_DIR`, компрометации Vault-токена, атак на KMS-провайдера.
- Логи содержат только имена и статусы; значения не логируются.
- KMS-бэкенд не является хранилищем секретов и не даёт ложного ощущения
  защиты: `get`/`rotate` для него явно не поддерживаются.
