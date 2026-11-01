<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Port;

interface CanGenerateGuid
{
    /**
     * @return non-empty-string
     */
    public function makeGuid(): string;
}
