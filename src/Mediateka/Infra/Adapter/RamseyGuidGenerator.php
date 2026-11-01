<?php

declare(strict_types=1);

namespace App\Mediateka\Infra\Adapter;

use App\Mediateka\Business\Port\CanGenerateGuid;
use App\Utils\Service\AbstractRamseyGuidGenerator;

final class RamseyGuidGenerator extends AbstractRamseyGuidGenerator implements CanGenerateGuid
{
}
