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
 */
readonly class SozdatWorkspace implements JsonBodySerializableInterface
{
    /**
     * @param list<string> $projectIds
     */
    public function __construct(
        public string $name = '',
        public string $branch = '',
        public array $projectIds = [],
        public string $prompt = '',
        public string $credentialId = '',
    ) {
    }
}
