<?php

namespace App\Unitman\Business\Model\SobitieIzHranilisha;

use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

final class DannieSobitiya
{
    /**
     * @param string      $vetka    ветка, к которой относится событие. Для
     *                              {@see TipSobitiya::VETKA_SMERZHENA} это
     *                              ветка юнита, а не целевая ветка мерджа.
     * @param string|null $celVetka целевая ветка мерджа. `null` для событий,
     *                              у которых нет направления слияния.
     */
    public function __construct(
        public readonly TipSobitiya $tipSobitiya,
        public readonly string $vetka,
        public readonly int $unixtime,
        public readonly ?string $celVetka = null,
    ) {
    }

}
