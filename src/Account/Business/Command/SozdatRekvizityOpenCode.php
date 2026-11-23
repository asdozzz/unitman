<?php

declare(strict_types=1);

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Добавление реквизитов подключения к OpenCode.
 *
 * API-ключ приходит открытым текстом (HTTPS) и сразу шифруется в UseCase,
 * поэтому в БД, логах и ответах API открытого ключа не остаётся.
 *
 * `provider` по умолчанию `custom`; `models` — набор моделей; `defaultModel`
 * пустой означает «первая модель набора». Поле `model` сохранено как legacy:
 * если `models` пуст, а `model` заполнен, набором становится `[model]`.
 */
readonly class SozdatRekvizityOpenCode implements JsonBodySerializableInterface
{
    /**
     * @param list<string> $models
     */
    public function __construct(
        public string $name = '',
        public string $url = '',
        public string $model = '',
        public string $apiKey = '',
        public string $provider = 'custom',
        public array $models = [],
        public string $defaultModel = '',
    ) {
    }
}
