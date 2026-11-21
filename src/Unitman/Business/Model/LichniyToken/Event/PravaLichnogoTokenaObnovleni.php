<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\LichniyToken\Event;

final class PravaLichnogoTokenaObnovleni
{
    /**
     * @param string[] $proektySPushom
     */
    public function __construct(
        public readonly string $tokenId,
        public readonly string $obyomPrav,
        public readonly array $proektySPushom,
        public readonly int $proverenoV,
    ) {
    }
}
