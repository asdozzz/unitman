<?php

declare(strict_types=1);

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Запрос live-списка моделей у провайдера OpenCode.
 *
 * Ключ берётся либо из сохранённых реквизитов (`credentialId`, владелец из JWT),
 * либо из `apiKey` — одноразово, без сохранения.
 */
readonly class SpisokModeleiProvaydera implements JsonBodySerializableInterface
{
    public function __construct(
        public string $provider = '',
        public ?string $apiKey = null,
        public ?string $credentialId = null,
    ) {
    }
}
