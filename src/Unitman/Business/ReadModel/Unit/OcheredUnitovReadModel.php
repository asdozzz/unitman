<?php

namespace App\Unitman\Business\ReadModel\Unit;

final class OcheredUnitovReadModel
{
    const SBORKA = 'SBORKA';
    const OBNOVLENIE = 'OBNOVLENIE';
    const PODGOTOVKA = 'PODGOTOVKA';
    const SBROS_PODGOTOVKI = 'SBROS_PODGOTOVKI';
    const ZAPUSK = 'ZAPUSK';
    const OSTANOVKA = 'OSTANOVKA';
    const UDALENIE = 'UDALENIE';

    const IZMENENIYE_VETKI = 'IZMENENIYE_VETKI';
    const DEISTVIE = 'DEISTVIE';

    public function __construct(
        public readonly int $id,
        public readonly string $unitId,
        public readonly string $queueName,
    )
    {
    }

}
