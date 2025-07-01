<?php

namespace App\Runner\Business\Model\GolangRunner\Unit;

final class ResultatOstanovkiUnita
{
    /**
     * @param array<Step> $Steps
     * */
    public function __construct(public readonly int $Success,public array $Steps, public readonly string $ResponseId = "")
    {
    }
}
