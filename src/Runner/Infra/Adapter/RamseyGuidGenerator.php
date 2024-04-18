<?php

namespace App\Runner\Infra\Adapter;

use App\Runner\Business\Port\CanGenerateGuid;
use App\Utils\Service\AbstractRamseyGuidGenerator;

final class RamseyGuidGenerator extends AbstractRamseyGuidGenerator implements CanGenerateGuid
{

}
