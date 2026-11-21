<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\LichniyToken;

final class PereproveritLichniyToken
{
    public function __construct(public readonly string $id)
    {
    }
}
