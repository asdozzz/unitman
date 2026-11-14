<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Запрос снапшота шагов, которые раннер уже прислал через `saveSteps`.
 *
 * Прогресс нужен только для «живой» индикации: он не влияет на стейт-машину
 * хода и может быть пустым, если раннер ещё ничего не отправил.
 */
readonly class PoluchitProgressHoda implements JsonBodySerializableInterface
{
    public function __construct(
        public string $turnId = '',
    ) {
    }
}
