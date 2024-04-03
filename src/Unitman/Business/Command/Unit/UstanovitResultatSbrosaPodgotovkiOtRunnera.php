<?php

namespace App\Unitman\Business\Command\Unit;

final class UstanovitResultatSbrosaPodgotovkiOtRunnera
{
    public function __construct(public readonly string $id, public readonly int $success,public readonly array $steps)
    {
    }
}
