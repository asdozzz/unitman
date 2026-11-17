<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\Command\Unit\GetMyUnits;

interface CanGetMyUnits
{
    /**
     * Помимо сериализованной read-модели каждая строка содержит признаки занятости
     * `zanyat` и `prichinaZanyatosti`: они читаются из реестра захватов, а не из payload.
     *
     * @return array<int, array<string, mixed>>
     */
    function getMyUnits(GetMyUnits $command, string $authorId): array;
}
