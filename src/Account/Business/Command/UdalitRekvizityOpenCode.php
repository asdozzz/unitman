<?php

declare(strict_types=1);

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Удаление реквизитов OpenCode по идентификатору.
 */
readonly class UdalitRekvizityOpenCode implements JsonBodySerializableInterface
{
    public function __construct(
        public string $id = '',
    ) {
    }
}
