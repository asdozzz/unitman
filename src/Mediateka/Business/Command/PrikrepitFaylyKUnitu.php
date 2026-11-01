<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Command;

/**
 * Привязка ранее загруженных файлов (черновиков) к юниту.
 */
final class PrikrepitFaylyKUnitu
{
    /**
     * @param string[] $fileIds идентификаторы файлов
     */
    public function __construct(
        public readonly string $unitId,
        public readonly array $fileIds
    ) {
    }
}
