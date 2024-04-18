<?php

namespace App\Runner\Infra\Adapter;

use App\Runner\Business\Port\CanGenerateGuid;

final class MemoryGuidGenerator extends \App\Utils\Service\AbstractMemoryGuidGenerator implements CanGenerateGuid
{

}
