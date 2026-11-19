<?php

namespace App\Unitman\Business\Model\SobitieIzHranilisha;

/**
 * Разобранное тело события хранилища (вебхук GitLab/GitHub).
 *
 * Иммутабельный снимок: `raw` — исходная строка
 * `SobitieIzHranilisha::eventPayload`, `data` — результат её единственного
 * `json_decode(..., true)`. Адаптеры читают только `data`, поэтому payload
 * декодируется один раз на задание, а не по разу на каждый вызов адаптера.
 */
final class SobitiePayload
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $raw,
        public readonly array $data,
    ) {
    }
}
