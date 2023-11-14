<?php

namespace App\Runner\Business\Model;

final class ResultatSbrokiUnita
{
    public function __construct(public readonly bool $Success,public readonly string $Message,public readonly string $Config)
    {
    }
}
