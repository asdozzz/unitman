<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;

interface UmeetPeredavatUvedomleniyaOpenCode
{
    public function publish(UvedomlenieONovomSoobsheniiOpenCode $notification): void;
}
