<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

interface UmeetOpredelyatKanalUvedomleniyOpenCode
{
    public function kanalDlyaAccount(string $accountId): string;
}
