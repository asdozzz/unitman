<?php

namespace App\Unitman\Business\Command\Unit;

use App\Utils\Converter\JsonBodySerializableInterface;

readonly class PoluchitShagiZadachiUnita implements JsonBodySerializableInterface
{
    public function __construct(public string $prozesId, public string $zadachaId)
    {
    }

}
