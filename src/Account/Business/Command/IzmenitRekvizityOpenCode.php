<?php

declare(strict_types=1);

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Изменение реквизитов OpenCode.
 *
 * Все поля кроме `id` необязательны: переданное `null` означает «оставить как есть».
 * `apiKey` тоже необязателен — если он не передан, старый шифротекст сохраняется.
 */
readonly class IzmenitRekvizityOpenCode implements JsonBodySerializableInterface
{
    public function __construct(
        public string $id = '',
        public ?string $name = null,
        public ?string $url = null,
        public ?string $model = null,
        public ?string $apiKey = null,
    ) {
    }
}
