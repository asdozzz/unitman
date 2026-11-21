<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Unit;

/**
 * Персональная ссылка редактора.
 *
 * `ssylka` — либо полный URL редактора с подписанным токеном режима, либо
 * сам подписанный токен, если базовый URL редактора ещё не подготовлен.
 */
final class SsylkaRedaktoraReadModel
{
    /**
     * @param string $redaktorMode `view`|`edit`|`locked`
     * @param int $expiresAt unixtime истечения подписи
     */
    public function __construct(
        public readonly string $ssylka,
        public readonly string $redaktorMode,
        public readonly int $expiresAt,
    )
    {
    }
}
