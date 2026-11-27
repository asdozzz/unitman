<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

/**
 * Приём файла-артефакта от раннера.
 *
 * Содержимое уже проверено по размеру в контроллере, но use case повторяет
 * лимиты: команда может вызываться и из других входов, а инвариант «не больше
 * N байт на файл» обязан выполняться в бизнес-слое.
 */
final class PrinyatFajlHoda
{
    public function __construct(
        public readonly string $workflowId,
        public readonly string $workspaceId,
        public readonly string $name,
        public readonly string $contentType,
        public readonly string $content,
    ) {
    }
}
