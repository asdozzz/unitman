<?php

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\UuidGenerator;
use App\Utils\Service\AbstractRamseyGuidGenerator;
use Ramsey\Uuid\Uuid;

final class RamseyUuidGenerator extends \App\Utils\Service\AbstractRamseyGuidGenerator implements UuidGenerator
{

}
