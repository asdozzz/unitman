# WS-2: шифрование токенов репозиториев at rest

## Что изменилось

Токен доступа к репозиторию больше не хранится в открытом виде ни в
`Repo`-агрегате, ни в событиях, ни в проекции `repo_list`.

- Шифр: AES-256-GCM (`UmeetShifrovatSekrety` / `PersonalTokenOpenSslShifr`).
- Формат: `base64(nonce[12] || tag[16] || ciphertext)`.
- AAD: строковый `repoId`. Перестановка шифротекста между репозиториями
  не проходит проверку GCM.
- Ключ: `LICHNIY_TOKEN_CREDENTIALS_KEY` — base64 от 32 байт. Тот же ключ,
  что и у личных токенов хранилищ. **Без ключа данные не восстановить.**
- `tokenHint`: последние 4 символа токена, для токенов короче 8 символов —
  пустая строка. Необратим, используется в публичном DTO WS-1.

События `RepoWasAdded` / `CredentialsOfRepoWasChanged` теперь содержат
`tokenCipher` + `tokenHint` и не содержат `token`.

## Изменение учётных данных (write-only)

`POST /api/repo/changeCredentials`:

- `token` не передан, `null` или пустая строка — текущий секрет сохраняется.
  Если URL тоже не изменился, событие не порождается.
- `token` непустой — секрет шифруется, hint обновляется, порождается событие.

Это контракт для write-only UI WS-5.

## Legacy-события и upcasting

Старые payload'ы с полем `token` читаются через
`RepoTokenUpcastingMessageSerializer`: на границе десериализации открытое
значение шифруется (AAD = `repo_id`) и заменяется на `token_cipher` /
`token_hint` **только в памяти**. Сохранённый payload в БД не переписывается.
Если ключ не настроен, чтение legacy-события падает с явной ошибкой
(`repo_token.key_not_configured` / `lichniy_token.key_not_configured`) —
silent plaintext fallback отсутствует.

## Opt-in миграция (только по явной команде)

```bash
# dry-run (по умолчанию) — только количество legacy-строк
php bin/console app:security:encrypt-repo-tokens

# перезапись старых payload'ов event store и repo_list
php bin/console app:security:encrypt-repo-tokens --apply
```

- Выполняется в одной транзакции.
- Перед изменением создаёт backup-таблицы:
  `unitman_event_store_repo_token_backup`, `repo_list_repo_token_backup`.
- Идемпотентна: строки без открытого `token` не трогаются, повторный запуск
  возвращает 0 и не дублирует backup.
- Сохраняет все остальные поля, включая `repoUrl` и порядок строк.
- Требует координации с WS-12 (регламент миграции/бэкапов).

Read-модель `repo_list` дополнительно перешифровывается Doctrine-миграцией
`Version20261126170000` (тоже идемпотентно, с backup-таблицей). Event store
эта миграция не переписывает — только opt-in команда.

## Rollback и потеря ключа

- Автоматический `down()` невозможен: обратное преобразование шифротекста в
  открытый вид требует ключа. Миграция оставляет backup-таблицы.
- При потере ключа восстановление возможно только из backup-таблиц
  (до применения `--apply`) либо из внешнего бэкапа БД.
- Ротация ключа не поддержана: нужен отдельный re-encrypt-сценарий (WS-9).

## WS-9 handoff

WS-9 должен предоставить управляемый провайдер ключа вместо прямого
`LICHNIY_TOKEN_CREDENTIALS_KEY`; контракт `UmeetShifrovatSekrety` и формат
шифротекста при этом менять не требуется.
