<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Создание workspace-сессии OpenCode.
 *
 * Пользователь выбирает реквизиты, ветку, набор проектов и первый промпт.
 * Список проектов проверяется в Unitman до создания сессии: недоступный или
 * продублированный проект не должен попасть в снимок.
 *
 * `branch` — общая ветка workspace по умолчанию. `projectBranches` —
 * необязательные переопределения ветки для отдельных проектов; проект без
 * переопределения использует общую `branch`.
 */
readonly class SozdatWorkspace implements JsonBodySerializableInterface
{
    /**
     * @param list<string> $projectIds
     * @param list<array{projectId?: mixed, branch?: mixed}> $projectBranches
     * @param list<string> $fileIds
     */
    public function __construct(
        public string $name = '',
        public string $branch = '',
        public array $projectIds = [],
        public string $prompt = '',
        public string $credentialId = '',
        public array $projectBranches = [],
        public ?string $model = null,
        public array $fileIds = [],
    ) {
    }
}
