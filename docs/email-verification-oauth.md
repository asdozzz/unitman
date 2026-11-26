# Подтверждение email и OAuth

## Самостоятельная регистрация

1. `POST /api/account/requestRegistration` создаёт аккаунт `ROLE_PENDING` с
   `is_email_verified = false` и отправляет 6-значный код на email.
2. Пользователь вводит код на `/verify-email`.
3. `POST /api/account/verifyEmail` подтверждает email и отправляет
   администраторам уведомление о заявке на активацию.
4. Администратор активирует аккаунт через `/api/account/activateByAdmin`.
5. До активации пользователь видит только `/pending`.

`is_email_verified = false` блокирует активацию администратором: сначала
пользователь должен подтвердить email.

## Добавление администратором

`POST /api/account/register` создаёт сразу активный и подтверждённый аккаунт.
Пароль генерирует система и отправляет письмом.

## OAuth Google/GitHub

Поток:

1. `GET /api/oauth/{provider}/redirect` — backend формирует подписанный
   `state` и редиректит на провайдера.
2. `GET /api/oauth/{provider}/callback` — обмен кода на токен, получение
   verified email, поиск/создание аккаунта.
3. Backend редиректит на frontend `/oauth/callback?code=<one-time>`.
4. Frontend обменивает одноразовый код через `POST /api/oauth/exchange` на
   JWT и refresh-токен.

Правила:

- провайдер обязан вернуть verified email;
- если `SocialIdentity` уже существует, вход идёт в связанный аккаунт;
- если verified email уже есть в `jwt_user`, identity привязывается к
  существующему аккаунту; пароль и роли не меняются;
- если email новый, создаётся `ROLE_PENDING`-аккаунт с подтверждённым email,
  администраторы сразу получают уведомление;
- заблокированный или системный аккаунт — ошибка.

## Переменные окружения

```dotenv
EMAIL_VERIFICATION_TTL=900
EMAIL_VERIFICATION_MAX_ATTEMPTS=5
EMAIL_VERIFICATION_RESEND_COOLDOWN=60

OAUTH_GOOGLE_CLIENT_ID=
OAUTH_GOOGLE_CLIENT_SECRET=
OAUTH_GITHUB_CLIENT_ID=
OAUTH_GITHUB_CLIENT_SECRET=
OAUTH_REDIRECT_BASE_URL=http://localhost:8082
OAUTH_FRONTEND_URL=http://localhost:8083
OAUTH_STATE_SECRET=change-me-in-production
OAUTH_STATE_TTL=600
OAUTH_EXCHANGE_CODE_TTL=120
```

`OAUTH_REDIRECT_BASE_URL` — публичный адрес backend. Callback-URL, который
нужно зарегистрировать у провайдера:

- Google: `${OAUTH_REDIRECT_BASE_URL}/api/oauth/google/callback`
- GitHub: `${OAUTH_REDIRECT_BASE_URL}/api/oauth/github/callback`

`OAUTH_FRONTEND_URL` — публичный адрес frontend, куда backend возвращает
пользователя после callback.

## Настройка провайдеров

### Google

1. Google Cloud Console → APIs & Services → Credentials → OAuth client ID.
2. Тип: Web application.
3. Authorized redirect URI: `.../api/oauth/google/callback`.
4. Scopes: `openid`, `email`, `profile`.
5. Заполнить `OAUTH_GOOGLE_CLIENT_ID` и `OAUTH_GOOGLE_CLIENT_SECRET`.

### GitHub

1. GitHub → Settings → Developer settings → OAuth Apps → New OAuth App.
2. Authorization callback URL: `.../api/oauth/github/callback`.
3. Заполнить `OAUTH_GITHUB_CLIENT_ID` и `OAUTH_GITHUB_CLIENT_SECRET`.

Если провайдер не настроен, `/api/oauth/providers` его не возвращает, и
кнопка входа не отображается.

## Миграции

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Миграция `Version20261126000000` добавляет `jwt_user.is_email_verified` и
таблицы `account_email_verification`, `account_social_identity`,
`oauth_exchange_code`.

## Проверка почты локально

По умолчанию `MAILER_DSN=null://null`, письма никуда не уходят. Для проверки
используйте Mailpit/MailHog и задайте реальный DSN, например:

```dotenv
MAILER_DSN=smtp://mailpit:1025
```
