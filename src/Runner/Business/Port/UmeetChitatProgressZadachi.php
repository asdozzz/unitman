<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

use App\Runner\Business\Model\RunnerProgress;

interface UmeetChitatProgressZadachi
{
    public function poluchit(string $token): ?RunnerProgress;
}
