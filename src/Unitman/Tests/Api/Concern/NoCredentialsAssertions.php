<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Api\Concern;

use PHPUnit\Framework\Assert;

/**
 * Общий рекурсивный сторож LT-4 для HTTP-ответов.
 *
 * Проверяет две независимые вещи на любом уровне вложенности:
 *  - имена ключей, нормализованные к запрещённым учётным полям;
 *  - значения, похожие на реальные учётные данные.
 *
 * `tokenHint` разрешён: это необратимая подсказка, а не токен.
 */
trait NoCredentialsAssertions
{
    /**
     * @return string[]
     */
    public static function findForbiddenCredentialKeys(mixed $node, string $trail = '$'): array
    {
        $forbidden = [
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

        $violations = [];

        if (!is_array($node)) {
            return $violations;
        }

        foreach ($node as $key => $value) {
            $key = (string) $key;
            $childTrail = $trail.'.'.$key;
            $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $key));

            if ($normalized !== 'tokenhint' && in_array($normalized, $forbidden, true)) {
                $violations[] = sprintf('%s (normalized "%s")', $childTrail, $normalized);
            }

            $violations = array_merge($violations, self::findForbiddenCredentialKeys($value, $childTrail));
        }

        return $violations;
    }

    /**
     * @return string[]
     */
    public static function findCredentialLookingValues(mixed $node, string $trail = '$'): array
    {
        $violations = [];

        if (is_array($node)) {
            foreach ($node as $key => $value) {
                $violations = array_merge(
                    $violations,
                    self::findCredentialLookingValues($value, $trail.'.'.$key),
                );
            }

            return $violations;
        }

        if (!is_string($node)) {
            return $violations;
        }

        $markers = [
            'oauth2:',
            '://user:pass@',
            'token@',
            'ghp_',
            'github_pat_',
            'glpat-',
            'Bearer ',
        ];

        foreach ($markers as $marker) {
            if (str_contains($node, $marker)) {
                $violations[] = sprintf('%s содержит маркер "%s"', $trail, $marker);
            }
        }

        // Общий случай userinfo-креденшелов в URL: scheme://user:password@host.
        if (preg_match('~://[^/\s:@]+:[^/\s@]+@~', $node) === 1) {
            $violations[] = sprintf('%s содержит user:password@ в URL', $trail);
        }

        return $violations;
    }

    public static function assertNoCredentialsInResponse(mixed $decoded, string $context): void
    {
        Assert::assertSame(
            [],
            self::findForbiddenCredentialKeys($decoded),
            sprintf('%s: в ответе найдены ключи учётных данных.', $context),
        );

        Assert::assertSame(
            [],
            self::findCredentialLookingValues($decoded),
            sprintf('%s: в ответе найдены значения, похожие на учётные данные.', $context),
        );
    }
}
