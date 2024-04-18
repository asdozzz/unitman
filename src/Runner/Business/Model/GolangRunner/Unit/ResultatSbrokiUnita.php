<?php

namespace App\Runner\Business\Model\GolangRunner\Unit;

final class ResultatSbrokiUnita
{
    /**
     * @param array<Step> $Steps
     * */
    public function __construct(public readonly int $Success,public readonly array $Steps, public readonly string $Config = '')
    {
    }
}
