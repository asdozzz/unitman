<?php

namespace App\Unitman\Business\Command\Project;

use App\Utils\Converter\JsonBodySerializableInterface;

final class ObnovitNastroikiHuka implements JsonBodySerializableInterface
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
