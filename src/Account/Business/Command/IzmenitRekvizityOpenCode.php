<?php

declare(strict_types=1);

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Изменение реквизитов OpenCode.
 *
 * Все поля кроме `id` необязательны: переданное `null` означает «оставить как есть».
 * `apiKey` тоже необязателен — если он не передан, старый шифротекст сохраняется.
 *
 * `provider`, `models`, `defaultModel` добавлены для мультимодельных реквизитов.
 * `model` сохранён как legacy: если `models` не передан, а `model` заполнен,
 * трактуем это как замену набора на `[model]` с дефолтом `model`.
 */
readonly class IzmenitRekvizityOpenCode implements JsonBodySerializableInterface
{
    /**
     * @param list<string>|null $models
     */
    public function __construct(
        public string $id = '',
        public ?string $name = null,
        public ?string $url = null,
        public ?string $model = null,
        public ?string $apiKey = null,
        public ?string $provider = null,
        public ?array $models = null,
        public ?string $defaultModel = null,
    ) {
    }
}
