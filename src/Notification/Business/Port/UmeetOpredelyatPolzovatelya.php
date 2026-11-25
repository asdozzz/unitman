<?php

declare(strict_types=1);

namespace App\Notification\Business\Port;

interface UmeetOpredelyatPolzovatelya
{
    public function tekushiyAccountId(): string;

    public function isAdmin(): bool;
}
