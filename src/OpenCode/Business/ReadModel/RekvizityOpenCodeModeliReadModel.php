<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Дефолтная модель и набор моделей реквизитов OpenCode.
 *
 * OpenCode не получает API-ключ: для выбора модели ему достаточно знать
 * дефолт и допустимый набор.
 */
final class RekvizityOpenCodeModeliReadModel
{
    /**
     * @param list<string> $models
     */
    public function __construct(
        public readonly string $defaultModel,
        public readonly array $models,
    ) {
    }
}
