<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Support;

use App\Unitman\Business\Model\VolumeType\VolumeType;
use App\Unitman\Business\Model\VolumeType\VolumeTypeFile;

/**
 * Общее хранилище каталога типов томов в памяти: типы и связи между ними.
 *
 * Репозитории каталога в SQL работают с двумя таблицами, поэтому в тестах
 * они делят одно хранилище — так `esliEstFayly` и `najtiKodyTipovPoFaylam`
 * видят те же данные, что и настоящие запросы.
 */
final class InMemoryKatalogStorage
{
    /**
     * @var array<string, VolumeType>
     */
    public array $types = [];

    /**
     * @var VolumeTypeFile[]
     */
    public array $links = [];
}
