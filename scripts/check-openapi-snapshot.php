<?php

declare(strict_types=1);

/**
 * Guard against OpenAPI snapshot drift between backend and frontend.
 *
 * The frontend commits a snapshot of `unitman/docs/openapi.json` at
 * `unitman-front/openapi/unitman.json` and generates its types from it.
 * If the backend dump is regenerated but the snapshot is not copied, the
 * frontend silently keeps using stale contracts.
 *
 * When the frontend checkout is not reachable (for example when only the
 * backend repository is mounted into a container) the check is skipped with
 * a notice instead of failing: the comparison is impossible in that layout.
 */

$root = dirname(__DIR__);
$backend = $root . '/docs/openapi.json';
$frontend = $root . '/../unitman-front/openapi/unitman.json';

if (!is_file($backend)) {
    fwrite(STDERR, "[check-openapi-snapshot] Не найден docs/openapi.json — сначала выполните composer openapi-dump\n");
    exit(1);
}

if (!is_file($frontend)) {
    echo "[check-openapi-snapshot] Пропуск: ../unitman-front/openapi/unitman.json не найден "
        . "(фронтенд не смонтирован рядом с бэкендом)\n";
    exit(0);
}

if (file_get_contents($backend) !== file_get_contents($frontend)) {
    fwrite(
        STDERR,
        "[check-openapi-snapshot] Снимок OpenAPI устарел: "
        . "скопируйте unitman/docs/openapi.json в unitman-front/openapi/unitman.json\n"
    );
    exit(1);
}

echo "[check-openapi-snapshot] Снимок OpenAPI синхронен с docs/openapi.json\n";
