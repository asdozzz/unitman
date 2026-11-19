<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Command;

/**
 * Завершение прямой загрузки: проверка объекта в хранилище и запись в реестр.
 */
final class ZavershitZagruzkuFayla
{
    public function __construct(public readonly string $uploadToken)
    {
    }
}
