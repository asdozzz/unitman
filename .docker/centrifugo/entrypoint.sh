#!/bin/sh
# Hardened Centrifugo v5 entrypoint for the local development stack.
#
# Mirrors the production entrypoint: renders the config with the origin
# allowlist from UNITMAN_CENTRIFUGO_ALLOWED_ORIGINS (comma-separated) and
# refuses to start when required values are missing or a wildcard is used.
# Secrets are passed through Centrifugo's own environment variables and are
# never written to disk or logged.
set -eu

: "${UNITMAN_CENTRIFUGO_SECRET:?CENTRIFUGO_SECRET is required (set it in .env.local)}"
: "${UNITMAN_CENTRIFUGO_API_KEY:?CENTRIFUGO_API_KEY is required (set it in .env.local)}"
: "${UNITMAN_CENTRIFUGO_ALLOWED_ORIGINS:?CENTRIFUGO_ALLOWED_ORIGINS is required (comma-separated browser origins)}"

case "$UNITMAN_CENTRIFUGO_ALLOWED_ORIGINS" in
    *'*'*)
        echo "centrifugo: wildcard origins are not allowed; set an explicit comma-separated allowlist" >&2
        exit 1
        ;;
esac

origins_json=""
old_ifs=$IFS
IFS=','
for origin in $UNITMAN_CENTRIFUGO_ALLOWED_ORIGINS; do
    origin=$(printf '%s' "$origin" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
    [ -n "$origin" ] || continue

    escaped=$(printf '%s' "$origin" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')
    if [ -n "$origins_json" ]; then
        origins_json="$origins_json,\"$escaped\""
    else
        origins_json="\"$escaped\""
    fi
done
IFS=$old_ifs

[ -n "$origins_json" ] || {
    echo "centrifugo: CENTRIFUGO_ALLOWED_ORIGINS resolved to an empty allowlist" >&2
    exit 1
}

sed "s#\"__CENTRIFUGO_ALLOWED_ORIGINS__\"#$origins_json#" /centrifugo/config.json > /tmp/centrifugo.json

export CENTRIFUGO_TOKEN_HMAC_SECRET_KEY="$UNITMAN_CENTRIFUGO_SECRET"
export CENTRIFUGO_API_KEY="$UNITMAN_CENTRIFUGO_API_KEY"

exec centrifugo "$@"
