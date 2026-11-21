<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\LichniyToken\Event;

final class LichniyTokenDobavlen
{
    /**
     * @param string[] $proektySPushom
     */
    public function __construct(
        public readonly string $tokenId,
        public readonly string $accountId,
        public readonly string $repoId,
        public readonly string $nazvanie,
        public readonly string $tokenCipher,
        public readonly string $tokenHint,
        public readonly string $obyomPrav,
        public readonly array $proektySPushom,
        public readonly int $proverenoV,
    ) {
    }
}
