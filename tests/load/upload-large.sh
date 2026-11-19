#!/bin/sh
# Загрузка крупных zip-архивов через POST /api/mediateka/upload.
#
# Методика повторяет baseline (plans/memory-baseline/report.md, раздел 7):
# multipart-загрузка файла с корректной zip-сигнатурой, замер time_total
# средствами curl. 200 МиБ сознательно не используется: Content-Length
# вместе с multipart-обёрткой превышает MEDIATEKA_MAX_FILE_SIZE и запрос
# отклоняется — это отдельный известный дефект, а не SLO.
#
# Использование (на хосте):
#   UNITMAN_USER=... UNITMAN_PASSWORD=... ./tests/load/upload-large.sh
#   ./tests/load/upload-large.sh 1 50          # только 1 и 50 МиБ
#
# Переменные окружения:
#   UNITMAN_BASE_URL  — по умолчанию http://localhost:8082 (порт из .env);
#                       внутри сети unitman_default: http://web:8080;
#   UNITMAN_USER / UNITMAN_PASSWORD — тестовый аккаунт;
#   UPLOAD_SIZES      — список размеров в МиБ (по умолчанию "1 50 199");
#   SLO_UPLOAD_1M_S / SLO_UPLOAD_50M_S / SLO_UPLOAD_199M_S — пороги в секундах
#                       (по умолчанию 0.2 / 5 / 20).

set -eu

BASE_URL="${UNITMAN_BASE_URL:-http://localhost:8082}"
BASE_URL="${BASE_URL%/}"
USER_NAME="${UNITMAN_USER:-}"
PASSWORD="${UNITMAN_PASSWORD:-}"
SIZES="${UPLOAD_SIZES:-1 50 199}"
TMP_DIR="${TMPDIR:-/tmp}"
RESPONSE_FILE="${TMP_DIR}/unitman-upload-response.json"

SLO_1M="${SLO_UPLOAD_1M_S:-0.2}"
SLO_50M="${SLO_UPLOAD_50M_S:-5}"
SLO_199M="${SLO_UPLOAD_199M_S:-20}"

if [ -n "${1:-}" ]; then
    SIZES="$*"
fi

if [ -z "$USER_NAME" ] || [ -z "$PASSWORD" ]; then
    echo "error: UNITMAN_USER and UNITMAN_PASSWORD must be set" >&2
    exit 2
fi

cleanup() {
    rm -f "${TMP_DIR}"/unitman-loadtest-*.zip "$RESPONSE_FILE"
}
trap cleanup EXIT INT TERM

json_token() {
    body="$1"

    if command -v jq >/dev/null 2>&1; then
        printf '%s' "$body" | jq -r '.token // empty'
        return
    fi

    printf '%s' "$body" \
        | sed -n 's/.*"token"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' \
        | head -n 1
}

echo "==> login: ${BASE_URL}/api/login"
LOGIN_BODY=$(curl -sS --max-time 30 \
    -H 'Content-Type: application/json' \
    -d "{\"username\":\"${USER_NAME}\",\"password\":\"${PASSWORD}\"}" \
    "${BASE_URL}/api/login") || {
    echo "error: login request failed" >&2
    exit 1
}

TOKEN=$(json_token "$LOGIN_BODY")

if [ -z "$TOKEN" ]; then
    echo "error: login failed, no token in response: $LOGIN_BODY" >&2
    exit 1
fi

slo_for_size() {
    case "$1" in
        1) printf '%s' "$SLO_1M" ;;
        50) printf '%s' "$SLO_50M" ;;
        199) printf '%s' "$SLO_199M" ;;
        *) printf '%s' '' ;;
    esac
}

make_zip() {
    size_mb="$1"
    file="${TMP_DIR}/unitman-loadtest-${size_mb}m.zip"

    # 1 МиБ = 1048576 байт; сигнатура zip — первые 4 байта "PK\003\004".
    dd if=/dev/zero of="$file" bs=1048576 count="$size_mb" 2>/dev/null
    printf 'PK\003\004' | dd of="$file" bs=1 conv=notrunc 2>/dev/null

    printf '%s' "$file"
}

failures=0
printf '\n%-6s %-8s %-8s %-12s %-10s %s\n' 'size' 'bytes' 'HTTP' 'time_total' 'SLO(s)' 'result'

for size_mb in $SIZES; do
    case "$size_mb" in
        1|50|199) ;;
        *)
            echo "warning: skipping unsupported size ${size_mb} MiB (expected 1, 50 or 199)" >&2
            continue
            ;;
    esac

    file=$(make_zip "$size_mb")
    bytes=$(wc -c < "$file" | tr -d ' ')
    limit=$(slo_for_size "$size_mb")

    result=$(curl -sS --max-time 180 \
        -o "$RESPONSE_FILE" \
        -w '%{http_code} %{time_total}' \
        -X POST "${BASE_URL}/api/mediateka/upload" \
        -H "Authorization: Bearer ${TOKEN}" \
        -F "file=@${file};filename=loadtest-${size_mb}m.zip;type=application/zip" \
        -F "name=loadtest-${size_mb}m.zip") || {
        printf '%-6s %-8s %-8s %-12s %-10s %s\n' "$size_mb" "$bytes" '-' '-' "$limit" 'curl-error'
        failures=$((failures + 1))
        rm -f "$file"
        continue
    }

    http_code=${result%% *}
    time_total=${result##* }

    ok=$(awk -v t="$time_total" -v l="$limit" 'BEGIN { exit !(t <= l) }' && echo yes || echo no)

    if [ "$http_code" = "200" ] && [ "$ok" = "yes" ]; then
        verdict='ok'
    else
        verdict='FAIL'
        failures=$((failures + 1))
        echo "  response: $(head -c 300 "$RESPONSE_FILE" 2>/dev/null)" >&2
    fi

    printf '%-6s %-8s %-8s %-12s %-10s %s\n' \
        "$size_mb" "$bytes" "$http_code" "$time_total" "$limit" "$verdict"

    rm -f "$file"
done

printf '\n'
if [ "$failures" -ne 0 ]; then
    echo "FAILED: ${failures} upload(s) exceeded SLO or returned an error" >&2
    exit 1
fi

echo "OK: all uploads within SLO"
