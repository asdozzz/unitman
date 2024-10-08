<?php
declare(strict_types=1);

namespace App\Runner\Business\Model\GolangRunner\Project;

final class ResultatOchistkiProekta
{
    /**
     * @param array<\App\Runner\Business\Model\GolangRunner\Unit\Step> $Steps
     * */
    public function __construct(public readonly int $Success,public readonly array $Steps)
    {
    }

}
