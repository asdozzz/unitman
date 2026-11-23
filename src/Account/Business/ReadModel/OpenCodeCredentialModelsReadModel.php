<?php

declare(strict_types=1);

namespace App\Account\Business\ReadModel;

/**
 * Дефолтная модель и набор моделей реквизитов OpenCode.
 *
 * Отдаётся модулям OpenCode/Runner через `AccountApi` для предвыбора модели.
 */
final class OpenCodeCredentialModelsReadModel
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
