<?php

namespace App\Unitman\Business\Command\Project;

final class ObnovitNastroikiHuka
{
    public function __construct(
        public string $id,
        public bool $avtosozdanie,
        public bool $avtoobnovlenie,
        public bool $avtoudalenie,
        public bool $obnovlenieBezSbrosaPodgotovki
    )
    {
    }
}
