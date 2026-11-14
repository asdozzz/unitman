<?php

declare(strict_types=1);

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Добавление реквизитов подключения к OpenCode.
 *
 * API-ключ приходит открытым текстом (HTTPS) и сразу шифруется в UseCase,
 * поэтому в БД, логах и ответах API открытого ключа не остаётся.
 */
readonly class SozdatRekvizityOpenCode implements JsonBodySerializableInterface
{
    public function __construct(
        public string $name = '',
        public string $url = '',
        public string $model = '',
        public string $apiKey = '',
    ) {
    }
}
