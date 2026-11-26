#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Контрактный сторож LT-4: в публичных контрактах (OpenAPI и AI-манифест)
 * не должно быть свойств-учётных данных.
 *
 * Проверяются:
 *  - `docs/openapi.json`: все 2xx JSON-схемы ответов (с разрешением $ref,
 *    allOf/anyOf/oneOf, массивов и вложенных объектов);
 *  - `openapi.tools.json` (AI-манифест, другая репозитория): рекурсивно все
 *    имена свойств, отсутствие `/api/internal` и broker/credential-операций.
 *
 * Узкий allowlist разрешён ТОЛЬКО для артефактов аутентификации, где токен и
 * есть легитимный результат: /api/login, /api/account/login,
 * /api/account/refreshToken, OAuth exchange/callback. Во всех остальных
 * операциях любое запрещённое имя свойства — ошибка.
 *
 * `tokenHint` разрешён всегда: это необратимая подсказка, а не токен.
 *
 * Использование:
 *   php scripts/check-no-credentials-in-contracts.php \
 *       --openapi=docs/openapi.json \
 *       --ai-manifest=../unitman-front/src/modules/ai-chat/generated/openapi.tools.json
 *   php scripts/check-no-credentials-in-contracts.php --self-test
 *
 * Коды выхода: 0 — чисто, 1 — найдены нарушения, 2 — ошибка использования/IO.
 */

/** @return string[] */
function unitman_contracts_forbidden_keys(): array
{
    return [
        'token',
        'refreshtoken',
        'password',
        'secret',
        'apikey',
        'privatekey',
        'tokencipher',
        'credential',
        'credentials',
        'authorization',
        'setcookie',
    ];
}

/**
 * Имена свойств, допустимые только внутри операций аутентификации.
 *
 * @return string[]
 */
function unitman_contracts_allowed_auth_token_keys(): array
{
    return ['token', 'accesstoken', 'refreshtoken'];
}

/**
 * Является ли операция артефактом аутентификации, где выдача токена легитимна.
 */
function unitman_contracts_is_auth_artifact_operation(string $path): bool
{
    $patterns = [
        '~^/api/login$~',
        '~^/api/account/login$~',
        '~^/api/account/refreshToken$~i',
        '~^/api/oauth/[^/]+/callback$~i',
        '~^/api/oauth/exchange$~i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $path) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * Единственная (кроме артефактов аутентификации) узкая уступка:
 * регистрация раннера возвращает одноразовый bootstrap-secret, без которого
 * протокол регистрации невозможен (секрет не сохраняется в открытом виде).
 */
function unitman_contracts_is_runner_registration_operation(string $path): bool
{
    return $path === '/api/runner/register';
}

/**
 * Допустимые (allowlisted) нормализованные имена свойств для операции.
 *
 * @return string[]
 */
function unitman_contracts_operation_allowed_keys(string $path): array
{
    if (unitman_contracts_is_auth_artifact_operation($path)) {
        return unitman_contracts_allowed_auth_token_keys();
    }

    if (unitman_contracts_is_runner_registration_operation($path)) {
        return ['secret'];
    }

    return [];
}

/**
 * Внутренние операции (broker/credentials) не являются публичным контрактом:
 * они не публикуются в AI-манифесте и проверяются отдельно на его стороне.
 *
 * @param array<string, mixed> $operation
 */
function unitman_contracts_is_internal_operation(string $path, array $operation): bool
{
    if ($path === '/api/internal' || str_starts_with($path, '/api/internal/')) {
        return true;
    }

    $x = $operation['x'] ?? null;
    if (is_array($x) && (($x['internal'] ?? false) === true || ($x['ai-enabled'] ?? null) === false)) {
        return true;
    }

    return ($operation['internal'] ?? false) === true;
}

function unitman_contracts_normalize_key(string $key): string
{
    return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $key));
}

function unitman_contracts_is_token_hint(string $key): bool
{
    return unitman_contracts_normalize_key($key) === 'tokenhint';
}

/**
 * Разрешает локальный JSON-pointer `$ref` (`#/components/schemas/Foo`).
 *
 * @param array<string, mixed> $document
 * @return array<string, mixed>|null
 */
function unitman_contracts_resolve_ref(string $ref, array $document): ?array
{
    if (!str_starts_with($ref, '#/')) {
        return null;
    }

    $node = $document;
    foreach (explode('/', substr($ref, 2)) as $segment) {
        $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
        if (!is_array($node) || !array_key_exists($segment, $node)) {
            return null;
        }
        $node = $node[$segment];
    }

    return is_array($node) ? $node : null;
}

/**
 * Рекурсивно обходит JSON-схему и собирает запрещённые имена свойств.
 *
 * @param array<string, mixed> $schema
 * @param array<string, mixed> $document
 * @param string[]             $violations
 * @param array<string, bool>  $seenRefs
 */
function unitman_contracts_walk_schema(
    array $schema,
    string $where,
    array $allowedKeys,
    array $document,
    array &$violations,
    array $seenRefs,
    string $jsonPath,
): void {
    if (isset($schema['$ref']) && is_string($schema['$ref'])) {
        $ref = $schema['$ref'];
        if (isset($seenRefs[$ref])) {
            return;
        }
        $target = unitman_contracts_resolve_ref($ref, $document);
        if ($target !== null) {
            $seenRefs[$ref] = true;
            unitman_contracts_walk_schema(
                $target,
                $where,
                $allowedKeys,
                $document,
                $violations,
                $seenRefs,
                $jsonPath,
            );
        }
    }

    if (isset($schema['properties']) && is_array($schema['properties'])) {
        foreach ($schema['properties'] as $name => $propertySchema) {
            $name = (string) $name;
            $childPath = $jsonPath.'.'.$name;
            $normalized = unitman_contracts_normalize_key($name);

            if (!unitman_contracts_is_token_hint($name)
                && in_array($normalized, unitman_contracts_forbidden_keys(), true)
                && !in_array($normalized, $allowedKeys, true)
            ) {
                $violations[] = sprintf(
                    '%s: forbidden property "%s" (normalized "%s")',
                    $where,
                    $childPath,
                    $normalized,
                );
            }

            if (is_array($propertySchema)) {
                unitman_contracts_walk_schema(
                    $propertySchema,
                    $where,
                    $allowedKeys,
                    $document,
                    $violations,
                    $seenRefs,
                    $childPath,
                );
            }
        }
    }

    if (isset($schema['items']) && is_array($schema['items'])) {
        unitman_contracts_walk_schema(
            $schema['items'],
            $where,
            $allowedKeys,
            $document,
            $violations,
            $seenRefs,
            $jsonPath.'[]',
        );
    }

    foreach (['allOf', 'anyOf', 'oneOf', 'prefixItems'] as $combiner) {
        if (!isset($schema[$combiner]) || !is_array($schema[$combiner])) {
            continue;
        }
        foreach ($schema[$combiner] as $index => $subSchema) {
            if (is_array($subSchema)) {
                unitman_contracts_walk_schema(
                    $subSchema,
                    $where,
                    $allowedKeys,
                    $document,
                    $violations,
                    $seenRefs,
                    $jsonPath.'|'.$combiner.'['.$index.']',
                );
            }
        }
    }

    if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
        unitman_contracts_walk_schema(
            $schema['additionalProperties'],
            $where,
            $allowedKeys,
            $document,
            $violations,
            $seenRefs,
            $jsonPath.'{additionalProperties}',
        );
    }
}

/**
 * @param array<string, mixed> $document
 * @return string[]
 */
function unitman_contracts_check_openapi(array $document): array
{
    $violations = [];

    $paths = $document['paths'] ?? [];
    if (!is_array($paths)) {
        return ['openapi: "paths" отсутствует или имеет неверный тип'];
    }

    foreach ($paths as $path => $operations) {
        if (!is_array($operations)) {
            continue;
        }

        $allowedKeys = unitman_contracts_operation_allowed_keys((string) $path);

        foreach ($operations as $method => $operation) {
            if (!is_array($operation)) {
                continue;
            }

            if (unitman_contracts_is_internal_operation((string) $path, $operation)) {
                continue;
            }

            $responses = $operation['responses'] ?? [];
            if (!is_array($responses)) {
                continue;
            }

            foreach ($responses as $status => $response) {
                if (substr((string) $status, 0, 1) !== '2' || !is_array($response)) {
                    continue;
                }

                $content = $response['content'] ?? [];
                if (!is_array($content)) {
                    continue;
                }

                foreach ($content as $mediaType => $media) {
                    if (stripos((string) $mediaType, 'json') === false || !is_array($media)) {
                        continue;
                    }
                    $schema = $media['schema'] ?? null;
                    if (!is_array($schema)) {
                        continue;
                    }

                    $where = sprintf(
                        '%s %s -> %s',
                        strtoupper((string) $method),
                        (string) $path,
                        (string) $status,
                    );

                    unitman_contracts_walk_schema(
                        $schema,
                        $where,
                        $allowedKeys,
                        $document,
                        $violations,
                        [],
                        '$',
                    );
                }
            }
        }
    }

    return $violations;
}

/**
 * Рекурсивно проверяет все имена свойств манифеста.
 *
 * @param mixed    $node
 * @param string[] $violations
 */
function unitman_contracts_walk_manifest_keys(mixed $node, string $trail, array &$violations): void
{
    if (is_array($node)) {
        foreach ($node as $key => $value) {
            $key = (string) $key;
            $childTrail = $trail.'/'.$key;
            if (!unitman_contracts_is_token_hint($key)
                && in_array(unitman_contracts_normalize_key($key), unitman_contracts_forbidden_keys(), true)
            ) {
                $violations[] = sprintf('manifest: forbidden property "%s"', $childTrail);
            }
            unitman_contracts_walk_manifest_keys($value, $childTrail, $violations);
        }
    }
}

/**
 * @param array<string, mixed> $manifest
 * @return string[]
 */
function unitman_contracts_check_manifest(array $manifest): array
{
    $violations = [];

    unitman_contracts_walk_manifest_keys($manifest, '$', $violations);

    $paths = $manifest['paths'] ?? [];
    if (is_array($paths)) {
        foreach ($paths as $path => $operations) {
            $path = (string) $path;
            if ($path === '/api/internal' || str_starts_with($path, '/api/internal/')) {
                $violations[] = sprintf('manifest: internal path "%s"', $path);
            }

            if (is_array($operations)) {
                foreach ($operations as $operation) {
                    if (!is_array($operation)) {
                        continue;
                    }
                    $operationId = (string) ($operation['operationId'] ?? '');
                    if ($operationId !== ''
                        && preg_match('/(broker|credential|issue_runner)/i', $operationId) === 1
                    ) {
                        $violations[] = sprintf(
                            'manifest: broker/credential operation "%s" at "%s"',
                            $operationId,
                            $path,
                        );
                    }
                }
            }
        }
    }

    return $violations;
}

/**
 * @param array<string, mixed> $document
 * @return array<string, mixed>
 */
function unitman_contracts_with_selftest_openapi_violation(array $document): array
{
    $document['paths']['/api/__ws11_selftest__/leak'] = [
        'post' => [
            'operationId' => 'ws11_selftest_leak',
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'privateKey' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    return $document;
}

/**
 * @param array<string, mixed> $manifest
 * @return array<string, mixed>
 */
function unitman_contracts_with_selftest_manifest_violation(array $manifest): array
{
    $manifest['paths']['/api/internal/ws11-selftest'] = [
        'post' => [
            'operationId' => 'issue_runner_repo_credentials_ws11_selftest',
            'responses' => [
                '200' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'apiKey' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    return $manifest;
}

/**
 * @param array<string, mixed> $document
 */
function unitman_contracts_write_json_temp(array $document): string
{
    $temp = tempnam(sys_get_temp_dir(), 'ws11-contract-');
    if ($temp === false) {
        throw new RuntimeException('Не удалось создать временный файл.');
    }
    file_put_contents($temp, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return $temp;
}

/**
 * @param array<string, mixed> $document
 * @return array<string, mixed>
 */
function unitman_contracts_read_json_file(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException(sprintf('Не удалось прочитать "%s".', $path));
    }
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('"%s" не содержит JSON-объект.', $path));
    }

    return $decoded;
}

/**
 * Self-test: копирует реальные файлы во временные, внедряет синтетическое
 * нарушение и доказывает, что проверка возвращает непустой список нарушений.
 */
function unitman_contracts_run_self_test(?string $openapiPath, ?string $manifestPath): int
{
    $openapi = $openapiPath !== null && is_file($openapiPath)
        ? unitman_contracts_read_json_file($openapiPath)
        : ['openapi' => '3.1.0', 'paths' => []];

    $tempOpenapi = unitman_contracts_write_json_temp(
        unitman_contracts_with_selftest_openapi_violation($openapi),
    );

    $manifest = $manifestPath !== null && is_file($manifestPath)
        ? unitman_contracts_read_json_file($manifestPath)
        : ['openapi' => '3.1.0', 'paths' => []];

    $tempManifest = unitman_contracts_write_json_temp(
        unitman_contracts_with_selftest_manifest_violation($manifest),
    );

    $ok = true;

    try {
        $openapiViolations = unitman_contracts_check_openapi(
            unitman_contracts_read_json_file($tempOpenapi),
        );
        if ($openapiViolations === []) {
            fwrite(STDERR, "self-test: синтетическое нарушение OpenAPI НЕ обнаружено.\n");
            $ok = false;
        } else {
            fwrite(STDOUT, "self-test: OpenAPI-нарушение обнаружено (".count($openapiViolations).").\n");
        }

        $manifestViolations = unitman_contracts_check_manifest(
            unitman_contracts_read_json_file($tempManifest),
        );
        if ($manifestViolations === []) {
            fwrite(STDERR, "self-test: синтетическое нарушение AI-манифеста НЕ обнаружено.\n");
            $ok = false;
        } else {
            fwrite(STDOUT, "self-test: AI-манифест-нарушение обнаружено (".count($manifestViolations).").\n");
        }
    } finally {
        @unlink($tempOpenapi);
        @unlink($tempManifest);
    }

    if (!$ok) {
        return 1;
    }

    fwrite(STDOUT, "self-test OK: проверка обнаруживает внедрённые нарушения.\n");

    return 0;
}

/**
 * @param string[] $argv
 */
function unitman_contracts_main(array $argv): int
{
    $options = getopt('', ['openapi::', 'ai-manifest::', 'self-test', 'help']);

    if (isset($options['help'])) {
        fwrite(STDOUT, "Usage: php scripts/check-no-credentials-in-contracts.php "
            ."[--openapi=PATH] [--ai-manifest=PATH] [--self-test]\n");
        return 0;
    }

    $defaultOpenapi = __DIR__.'/../docs/openapi.json';
    $defaultManifest = __DIR__.'/../../unitman-front/src/modules/ai-chat/generated/openapi.tools.json';

    $openapiPath = is_string($options['openapi'] ?? null) ? $options['openapi'] : $defaultOpenapi;
    $manifestExplicit = is_string($options['ai-manifest'] ?? null);
    $manifestPath = $manifestExplicit ? $options['ai-manifest'] : $defaultManifest;

    if (isset($options['self-test'])) {
        if (!is_file($openapiPath)) {
            fwrite(STDERR, sprintf("self-test: OpenAPI-файл не найден: %s\n", $openapiPath));
            return 2;
        }
        return unitman_contracts_run_self_test($openapiPath, is_file($manifestPath) ? $manifestPath : null);
    }

    if (!is_file($openapiPath)) {
        fwrite(STDERR, sprintf("OpenAPI-файл не найден: %s\n", $openapiPath));
        return 2;
    }

    $violations = [];

    try {
        $openapi = unitman_contracts_read_json_file($openapiPath);
        $violations = array_merge($violations, unitman_contracts_check_openapi($openapi));

        if (!is_file($manifestPath)) {
            $message = sprintf("AI-манифест не найден: %s\n", $manifestPath);
            if ($manifestExplicit) {
                fwrite(STDERR, $message);
                return 2;
            }
            fwrite(STDERR, "WARN: ".$message."Проверка манифеста пропущена (не передан --ai-manifest).\n");
        } else {
            $manifest = unitman_contracts_read_json_file($manifestPath);
            $violations = array_merge($violations, unitman_contracts_check_manifest($manifest));
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, 'ERROR: '.$exception->getMessage()."\n");
        return 2;
    }

    if ($violations !== []) {
        fwrite(STDERR, "Найдены учётные данные в контрактах:\n");
        foreach ($violations as $violation) {
            fwrite(STDERR, '  - '.$violation."\n");
        }
        return 1;
    }

    fwrite(STDOUT, "OK: в публичных контрактах нет учётных данных.\n");

    return 0;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(unitman_contracts_main($argv));
}
