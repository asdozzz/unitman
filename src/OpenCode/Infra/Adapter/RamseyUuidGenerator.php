<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Adapter;

use App\OpenCode\Business\Port\UuidGenerator;
use App\Utils\Service\AbstractRamseyGuidGenerator;

final class RamseyUuidGenerator extends AbstractRamseyGuidGenerator implements UuidGenerator
{
}