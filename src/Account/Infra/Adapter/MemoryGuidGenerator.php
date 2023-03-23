<?php

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\UuidGenerator;

final class MemoryGuidGenerator extends \App\Utils\Service\AbstractMemoryGuidGenerator implements UuidGenerator
{

}
