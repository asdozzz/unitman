<?php

declare(strict_types=1);

namespace App\Runner\Business\Model\OpenCode;

/**
 * Реквизиты подключения к OpenCode, собранные для одного хода.
 *
 * Содержит расшифрованный API-ключ, поэтому объект живёт только в памяти
 * процесса на время формирования команды активити и никогда не сериализуется
 * в БД, логи, Centrifugo или result_json.
 */
final class RekvizityOpenCode
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $model,
        public readonly string $apiKey,
    ) {
    }
}
