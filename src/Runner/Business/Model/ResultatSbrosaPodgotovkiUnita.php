<?php

namespace App\Runner\Business\Model;

final class ResultatSbrosaPodgotovkiUnita
{
    /**
     * @param array<Step> $Steps
     * */
    public function __construct(public readonly string $UnitId, public readonly int $Success,public readonly array $Steps)
    {
    }
}
