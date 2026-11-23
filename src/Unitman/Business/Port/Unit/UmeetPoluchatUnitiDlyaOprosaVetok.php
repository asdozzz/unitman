<?php

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\ReadModel\Unit\UnitaDlyaOprosaVetok;

interface UmeetPoluchatUnitiDlyaOprosaVetok
{
    /**
     * Юниты активных проектов, у которых Git-вебхук ещё не подтверждён.
     *
     * @return UnitaDlyaOprosaVetok[]
     */
    public function poluchitUnitiDlyaOprosaVetok(): array;
}
