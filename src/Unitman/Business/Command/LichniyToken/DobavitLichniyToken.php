<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\LichniyToken;

final class DobavitLichniyToken
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $token,
        public readonly string $nazvanie,
    ) {
    }
}
