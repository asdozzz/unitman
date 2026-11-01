<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Support;

use App\Mediateka\Business\Port\CanGenerateGuid;

/**
 * Детерминированный генератор идентификаторов: в тестах ключ хранилища должен быть предсказуем.
 */
final class FixedGuidGenerator implements CanGenerateGuid
{
    public function __construct(private readonly string $guid = 'file-id-1')
    {
    }

    public function makeGuid(): string
    {
        return $this->guid;
    }
}
