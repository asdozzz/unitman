# Перевод сообщений исключений

Все сообщения, которые API отдаёт клиенту в поле `message`, хранятся в коде в виде
ключей-точек (`unit.not_found`, `project.webhook.not_found`). Наружу они попадают уже
переведёнными на язык пользователя.

## 1. Канон — английский

| Локаль | Роль | Файл |
|--------|------|------|
| `en` | канон (источник истины) | `translations/messages.en.php` |
| `ru` | перевод | `translations/messages.ru.php` |

Канон задаётся переменной окружения `DEFAULT_LOCALE` (по умолчанию `en`) и используется как
fallback: если в словаре выбранной локали ключа нет, Symfony возьмёт значение из канона.
Если ключа нет и в каноне — вернётся сам ключ (это считается ошибкой, см. аудит).

Язык по умолчанию для новых аккаунтов задаёт отдельная переменная `ACCOUNT_DEFAULT_LOCALE`
(`ru`). Она не влияет на fallback.

Поддерживаемые локали перечислены в `App\Utils\Model\Locale\SupportedLocale`.
Добавление новой локали = новый case в этом enum + новый файл `translations/messages.<locale>.php`.

## 2. Как выбирается язык ответа

`App\Utils\Converter\ResolveLocaleListener` (событие `kernel.request`, приоритет `0` —
после firewall) выставляет локаль запроса в порядке приоритета:

1. локаль авторизованного аккаунта (`accounts.locale`, приходит в JWT);
2. заголовок `Accept-Language`;
3. канон (`en`).

Локаль передаётся **явным аргументом** в транслятор. Глобальный `setLocale()` не вызывается:
воркеры RoadRunner живут долго, и глобальное состояние протекло бы между запросами.

## 3. Как ключ превращается в текст

`App\Utils\Service\ExceptionTranslator`:

```php
// BaseExceptionListener / контроллеры
$this->exceptionTranslator->translateThrowable($e);
$this->exceptionTranslator->translate('mediateka.file.not_found');
```

- строка, похожая на ключ (`a.b.c`), переводится;
- произвольный текст (например, из сторонней библиотеки) возвращается как есть;
- поддерживается суффикс деталей: `unit.obrabotka_prozesa.tip_ne_opredelen: build`
  переводится по ключу до `": "`, а `build` дописывается в конец.

## 4. Как добавить или поправить сообщение

1. В коде бросаем **ключ**, а не текст:
   ```php
   throw new \DomainException('unit.new_error_key');
   ```
2. Добавляем ключ в **канон** `translations/messages.en.php` в подходящую группу.
3. Добавляем перевод в `translations/messages.ru.php` с тем же ключом.
4. Прогоняем аудит (см. ниже) — он покажет и отсутствующие ключи, и «сирот».

Ключи группируются по модулю: `unit.`, `project.`, `account.`, `mediateka.`,
`initialization.`, `common.`, `utils.`. Внутри группы — вложенные массивы,
точка в ключе = вложенность массива.

> Исторические ключи местами содержат транслит и `camelCase`
> (`unit.projectId_is_empty`, `unit.config.resetPrepare_is_empty`).
> Новые ключи пишем в `snake_case`; существующие не переименовываем.

## 5. Аудит словарей

```bash
php bin/console app:translations:audit      # или: composer run check-translations
```

Проверяет:

- набор ключей каждой локали совпадает с каноном;
- нет пустых переводов;
- каждый ключ, брошенный из кода, есть в каноне (иначе — **ошибка**, exit code ≠ 0);
- ключи канона, на которые никто не ссылается, — предупреждение.

Команда входит в `composer run all-checks`. Дополнительно те же инварианты
проверяет `App\Utils\Tests\DictionariesConsistencyTest`.

## 6. Тесты

| Тест | Что покрывает |
|------|---------------|
| `src/Utils/Tests/Service/ExceptionTranslatorTest.php` | перевод по локали, fallback на канон, неизвестный ключ, свободный текст, суффикс деталей, нормализация локали |
| `src/Utils/Tests/Converter/ResolveLocaleListenerTest.php` | порядок выбора локали: аккаунт → `Accept-Language` → канон |
| `src/Utils/Tests/DictionariesConsistencyTest.php` | паритет словарей с каноном, отсутствие пустых значений |

```bash
docker compose exec web php vendor/bin/phpunit src/Utils/Tests
```

## 7. Связанные настройки

- `config/packages/translation.yaml` — `default_locale` и `fallbacks` из `%env(DEFAULT_LOCALE)%`;
- `src/Utils/di.php` — биндинг `$defaultLocale` для `ExceptionTranslator`;
- `src/Account/di.php` — биндинг `$defaultLocale` (`ACCOUNT_DEFAULT_LOCALE`) для регистрации аккаунтов.
