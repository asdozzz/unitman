<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\LichniyToken\Event;

final class LichniyTokenUdalen
{
    public function __construct(public readonly string $tokenId)
    {
    }
}
