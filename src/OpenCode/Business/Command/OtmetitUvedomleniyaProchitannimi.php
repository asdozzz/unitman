<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Захват уведомлений: перевод выбранных непрочитанных в прочитанные.
 *
 * `ids` обязателен и непуст, не более 100 уникальных идентификаторов.
 * Чужие и уже прочитанные уведомления запросом не затрагиваются.
 */
readonly class OtmetitUvedomleniyaProchitannimi implements JsonBodySerializableInterface
{
    public const MAX_IDS = 100;

    /**
     * @param list<string> $ids
     */
    public function __construct(
        public array $ids = [],
    ) {
    }
}
