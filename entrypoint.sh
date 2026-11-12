#!/bin/sh
# Точка входа контейнера web.
#
# Задачи при старте:
#   1. гарантировать наличие JWT-ключей (не перезаписывая смонтированные);
#   2. применить миграции;
#   3. опционально пересобрать проекции;
#   4. передать управление основной команде (RoadRunner) через exec.
set -e

# Ключи могут быть смонтированы снаружи (см. dc.mvp.yml). Флаг --overwrite
# инвалидировал бы все ранее выданные токены при каждом рестарте контейнера,
# поэтому генерируем только при отсутствии файлов.
if [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
    php bin/console lexik:jwt:generate-keypair --no-interaction
fi

php bin/console --no-interaction doctrine:migrations:migrate

# Полный ребилд проекций — тяжёлая и потенциально разрушительная операция,
# поэтому по умолчанию выключена и включается явно через окружение.
if [ "${UNITMAN_REBUILD_PROJECTIONS:-0}" = "1" ]; then
    php bin/console app:projections:rebuild all
fi

# exec обязателен: RoadRunner должен стать PID 1 и получать SIGTERM/SIGINT
# от `docker stop`. Именно на эти сигналы подписан app:service:start.
exec "$@"
