#!/bin/sh
# shellcheck disable=SC2016
# Регистрирует токен прогресса для /api/runner/saveSteps в Redis.
#
# RoadRunner KV (redis driver) хранит значения в формате PHP serialize(),
# поэтому пишем не голый unixtime, а строку s:<len>:"<unixtime>"; —
# именно это вернёт RedisService::get() в приложении.
#
# Использование:
#   tests/load/register-progress-token.sh <token> [redis-container] [docker-host]
#
# Примеры:
#   tests/load/register-progress-token.sh loadtest-k6
#   tests/load/register-progress-token.sh loadtest-k6 redis
#   tests/load/register-progress-token.sh loadtest-k6 redis ssh://user@host
#
# Переменные окружения:
#   REDIS_CONTAINER          — имя контейнера, если не задан 2-м аргументом (redis);
#   DOCKER_HOST_OVERRIDE     — то же, что 3-й аргумент;
#   TOKEN_TTL_SECONDS        — TTL ключа (7200).

set -eu

TOKEN="${1:-}"
REDIS_CONTAINER="${2:-${REDIS_CONTAINER:-redis}}"
DOCKER_HOST_VALUE="${3:-${DOCKER_HOST_OVERRIDE:-}}"
TTL="${TOKEN_TTL_SECONDS:-7200}"

usage() {
    cat >&2 <<'EOF'
Usage: register-progress-token.sh <token> [redis-container] [docker-host]

  token           значение responseId для saveSteps (обязательно)
  redis-container имя контейнера Redis (по умолчанию redis)
  docker-host     опциональный -H для docker (например, ssh://user@host)

Environment: REDIS_CONTAINER, DOCKER_HOST_OVERRIDE, TOKEN_TTL_SECONDS.
EOF
}

if [ -z "$TOKEN" ] || [ "$TOKEN" = "-h" ] || [ "$TOKEN" = "--help" ]; then
    usage
    exit 2
fi

case "$TOKEN" in
    *[!A-Za-z0-9._:-]*)
        echo "error: token may contain only A-Z, a-z, 0-9, '.', '_', ':' and '-'" >&2
        exit 2
        ;;
esac

case "$TTL" in
    ''|*[!0-9]*)
        echo "error: TOKEN_TTL_SECONDS must be a positive integer" >&2
        exit 2
        ;;
esac

UNIXTIME=$(date +%s)
VALUE="s:${#UNIXTIME}:\"${UNIXTIME}\";"
KEY="runner.progress.token.${TOKEN}"

# Переменные REDIS_USER/REDIS_USER_PASSWORD раскрывает shell ВНУТРИ
# контейнера, поэтому скрипт redis-cli намеренно в одинарных кавычках
# (SC2016 отключён для файла выше).
redis_cli() {
    script="$1"
    shift

    if [ -n "$DOCKER_HOST_VALUE" ]; then
        docker -H "$DOCKER_HOST_VALUE" exec "$REDIS_CONTAINER" sh -c "$script" sh "$@"
    else
        docker exec "$REDIS_CONTAINER" sh -c "$script" sh "$@"
    fi
}

# SET идемпотентен: повторный вызов перезаписывает значение и сбрасывает TTL.
redis_cli \
    'redis-cli --user "$REDIS_USER" --pass "$REDIS_USER_PASSWORD" --no-auth-warning SET "$1" "$2" EX "$3"' \
    "$KEY" "$VALUE" "$TTL" >/dev/null

TTL_ACTUAL=$(redis_cli \
    'redis-cli --user "$REDIS_USER" --pass "$REDIS_USER_PASSWORD" --no-auth-warning TTL "$1"' \
    "$KEY" | tr -d '\r\n')

STORED=$(redis_cli \
    'redis-cli --user "$REDIS_USER" --pass "$REDIS_USER_PASSWORD" --no-auth-warning GET "$1"' \
    "$KEY" | tr -d '\r\n')

printf 'key: %s\n' "$KEY"
printf 'value: %s\n' "$VALUE"
printf 'stored: %s\n' "$STORED"
printf 'ttl: %s seconds\n' "$TTL_ACTUAL"

case "$TTL_ACTUAL" in
    ''|*[!0-9]*)
        echo "warning: could not read TTL back from Redis" >&2
        ;;
    0)
        echo "warning: TTL is 0 — token will not expire" >&2
        ;;
esac
