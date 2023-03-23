<?php

namespace App\Unitman\Business\Command\Project;

final class PostavitVOcheredNaUdalenie
{
    public function __construct(
        public readonly string $id,
    )
    {
    }
}
