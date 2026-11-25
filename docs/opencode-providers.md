# Провайдеры и модели OpenCode

Реквизиты подключения к OpenCode описывают не один «URL + модель», а
**провайдера**, базовый URL и **набор моделей** с дефолтной. Пользователь
выбирает провайдера из статического каталога, URL подставляется автоматически,
а список моделей можно загрузить у самого провайдера.

План и обоснование решений — [`plans/opencode-providers-models.md`](plans/opencode-providers-models.md:1).
Общий контракт сессий и ходов — [`docs/opencode-sessions.md`](docs/opencode-sessions.md:1).

## 1. Модель данных

- `provider` — код провайдера из каталога (13 значений, см. §2).
- `url` — базовый URL OpenAI-совместимого API. Для известного провайдера
  подставляется из каталога и игнорируется во входных данных; для `custom`
  обязателен (`account.opencode_credential.empty_url`).
- `models` — 1–50 непустых моделей длиной до 128 символов, без дубликатов.
- `defaultModel` — дефолтная модель набора; пустое значение означает «первая
  модель».
- Legacy-поле `model` сохранено как синоним дефолтной модели; колонка
  `opencode_credential.model` всегда равна `defaultModel`.

Набор моделей хранится в отдельной таблице `opencode_credential_model`
(`credential_id`, `model`, `is_default`), где частичный уникальный индекс
гарантирует ровно одну дефолтную строку на реквизиты. Замена набора при
изменении реквизитов выполняется в одной транзакции.

## 2. Каталог провайдеров

`GET /api/account/opencode/providers` возвращает статический каталог
`ProviderOpenCode[]`. Для каждого элемента: `code`, `name`, `baseUrl`,
`modelsPath`, `authStyle`, `supportsModelList`, `models` (стартовый список).

| Код | Название | baseUrl | Авторизация | Live-список |
|---|---|---|---|---|
| `openai` | OpenAI | `https://api.openai.com/v1` | Bearer | да |
| `anthropic` | Anthropic | `https://api.anthropic.com/v1` | `x-api-key` + `anthropic-version` | да |
| `google` | Google Gemini | `https://generativelanguage.googleapis.com/v1beta/openai` | Bearer | да |
| `openrouter` | OpenRouter | `https://openrouter.ai/api/v1` | Bearer | да |
| `deepseek` | DeepSeek | `https://api.deepseek.com/v1` | Bearer | да |
| `mistral` | Mistral | `https://api.mistral.ai/v1` | Bearer | да |
| `groq` | Groq | `https://api.groq.com/openai/v1` | Bearer | да |
| `xai` | xAI | `https://api.x.ai/v1` | Bearer | да |
| `together` | Together AI | `https://api.together.xyz/v1` | Bearer | да |
| `ollama` | Ollama | `http://localhost:11434/v1` | без ключа | нет |
| `opencode` | OpenCode Zen | `https://api.opencode.ai` | Bearer | нет |
| `opencode-go` | OpenCode Go | `https://opencode.ai/inference/go/openai/v1` | Bearer | да |
| `custom` | Другой OpenAI-совместимый | — (вводится вручную) | без ключа | нет |

`modelsPath` у всех провайдеров — `/models`. Стартовые списки моделей
(`models`) — только подсказка для UI. При live-запросе источник истины —
ответ провайдера; для `custom`, `ollama` и `opencode` live-запрос не
выполняется, и фронтенд работает со статическим списком и ручным вводом.

## 3. Загрузка списка моделей

`POST /api/account/opencode/spisokModelei`

```json
{
  "provider": "openai",
  "apiKey": "sk-...",
  "credentialId": "b1c2..."
}
```

- владелец берётся из JWT;
- если передан `credentialId`, ключ расшифровывается из сохранённых реквизитов
  и проверяется владение; `apiKey` в этом случае не нужен;
- если передан только `apiKey`, он используется один раз и **не сохраняется**;
- для известного провайдера запрос идёт на `baseUrl + modelsPath` из каталога;
  `baseUrl` из тела запроса игнорируется;
- для `custom`, `ollama`, `opencode` сразу возвращается `source: "static"`;
- ошибка провайдера не ломает форму: HTTP 200, `source: "static"`,
  статический список и `errorKey`.

Ответ `SpisokModelei`:

```json
{
  "provider": "openai",
  "models": ["gpt-4o", "gpt-4o-mini"],
  "source": "live",
  "errorKey": null
}
```

`source` принимает `live` (ответ провайдера) или `static` (список каталога).
`errorKey` — ключ перевода ошибки, `null` при успехе.

### 3.1 Разбор ответа провайдера

Адаптер принимает три формы:

- OpenAI: `data[].id`;
- Anthropic / Google: `models[].name`;
- плоский массив строк (fallback).

Результат нормализуется: `trim`, удаление пустых значений и дубликатов с
сохранением порядка. Пустой результат — `provider_empty_response`.

### 3.2 Ограничения live-запроса

| Параметр | Значение |
|---|---|
| Таймаут | 5 секунд (`timeout` и `max_duration`) |
| Лимит ответа | 1 МБ |
| Метод | `GET` |
| URL | только `baseUrl + modelsPath` из каталога |

## 4. Безопасность ключа

- Ключ передаётся **только в заголовке** (`Authorization: Bearer` либо
  `x-api-key`), никогда — в query-строке или теле запроса.
- Ключ не логируется и не возвращается ни в одном ответе; в списке реквизитов
  отдаётся только маска `apiKeyHint` (последние 4 символа).
- При загрузке по `credentialId` ключ расшифровывается на сервере и не покидает
  его в открытом виде.
- Одноразовый `apiKey` из тела `spisokModelei` не сохраняется.
- Соединение с провайдером — только по HTTPS (кроме локального `ollama`).

## 5. Коды ошибок

Все ключи — в пространстве `account.opencode_credential.*`; тексты в
[`translations/messages.ru.php`](translations/messages.ru.php:1) и
[`translations/messages.en.php`](translations/messages.en.php:1).

| Ключ | Когда возникает |
|---|---|
| `empty_provider` | пустой код провайдера |
| `unknown_provider` | код не из каталога |
| `empty_models` | набор моделей пуст |
| `too_many_models` | больше 50 моделей |
| `duplicate_model` | модель повторяется в наборе |
| `empty_model` | пустая строка в наборе |
| `model_too_long` | модель длиннее 128 символов |
| `model_not_available` | модель/дефолт не входит в набор реквизитов |
| `empty_url` | `custom` без URL |
| `provider_list_not_supported` | провайдер не поддерживает live-список |
| `provider_unavailable` | сетевая ошибка/таймаут |
| `provider_error` | HTTP-статус ≥ 400 |
| `provider_response_too_large` | ответ больше 1 МБ |
| `provider_invalid_response` | ответ не JSON-объект |
| `provider_empty_response` | провайдер вернул пустой список моделей |
| `empty_api_key` | live-запрос без ключа для провайдера, требующего авторизацию |

Ошибки валидации реквизитов бросаются внутри агрегата
[`OpenCodeCredential`](src/Account/Business/Model/OpenCode/OpenCodeCredential.php:1),
live-запроса — в
[`HttpZaprositModeliProvaydera`](src/Account/Infra/Adapter/HttpZaprositModeliProvaydera.php:1).

## 6. Использование моделей в сессиях

Модель хода разрешается по приоритету: `model` из запроса → модель сессии →
`defaultModel` реквизитов. Недопустимая модель отклоняется ключом
`account.opencode_credential.model_not_available`. Подробности — в
[`docs/opencode-sessions.md`](docs/opencode-sessions.md:1), раздел «Провайдеры и
несколько моделей».

## 7. Provider-сессии AI-чата

AI-чат использует отдельный слой политик provider-сессии. Политика задаётся
для каждого провайдера в `ProvayderOpenCode::politikaSessii()` и отдаётся
через `AccountApi::poluchitPolitikuSessiiProvaydera()`. Read model содержит
ровно поля `provider`, `transport`, `key`, `ttlSeconds`, `supportsAiChat`.

| Провайдер | `transport` | `key` | `ttlSeconds` | AI-чат |
|---|---|---|---|---|
| `opencode`, `opencode-go` | `header` | `x-opencode-session` | 300 | да |
| `openai`, `anthropic`, `openrouter`, `deepseek`, `mistral`, `groq`, `xai`, `together`, `ollama`, `custom` | `none` | — | 300 | да |
| `google` | `none` | — | 3600 | да |
| неизвестный провайдер | `unknown` | — | 300 | нет (`supportsAiChat=false`) |

Правила:

- `match` в `ProvayderOpenCode` не имеет `default`: новый провайдер без
  политики ломает выполнение, а не тихо получает `none`.
- `SessionTransportApplier` применяет политику к headers/body/query; для
  `unknown` выбрасывается `ai.chat.provider_session_transport_unknown`.
- `OpenCodeChatProxy` не содержит `if ($provider === 'opencode-go')`: заголовок
  ставит транспорт по политике.
- Provider-сессия живёт 300 секунд и ротируется по TTL; история
  пользовательской сессии при этом не удаляется.
- Неизвестный/неподдерживаемый провайдер возвращает
  `422 ai.chat.provider_not_supported`.
- Тесты-гейты: итерация по всем case провайдеров, проверка `ttlSeconds > 0`,
  проверка `supportsAiChat=false` для `unknown`, применение header/body/query/
  none и ротация после TTL.
