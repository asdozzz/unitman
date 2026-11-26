<?php

declare(strict_types=1);

namespace App\Utils\Env;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Превращает CORS_ALLOW_ORIGIN (список origin через запятую) в anchored regex.
 *
 * NelmioCorsBundle на этапе компиляции контейнера вызывает in_array() по
 * allow_origin, поэтому `%env(csv:...)%` (массив) там неприменим. Процессор
 * отдаёт строку-regex с экранированными origin: `~^(?:https://a|https://b)$~`.
 * Пустой список даёт regex, который не совпадает ни с чем (fail-closed).
 */
final class CorsAllowlistEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $value = $getEnv($name);

        // NelmioCorsBundle оборачивает значение в свои delimiters `{...}`,
        // поэтому отдаём regex без delimiters.
        if (!is_string($value) || trim($value) === '') {
            return '^(?!)$';
        }

        $origins = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $origin): bool => $origin !== '',
        ));

        if ($origins === []) {
            return '^(?!)$';
        }

        $quoted = array_map(
            static fn (string $origin): string => preg_quote(rtrim($origin, '/'), '~'),
            $origins,
        );

        return '^(?:' . implode('|', $quoted) . ')$';
    }

    public static function getProvidedTypes(): array
    {
        return ['cors_allowlist' => 'string'];
    }
}
