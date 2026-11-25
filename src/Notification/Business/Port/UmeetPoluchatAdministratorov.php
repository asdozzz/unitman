<?php

declare(strict_types=1);

namespace App\Notification\Business\Port;

interface UmeetPoluchatAdministratorov
{
    /**
     * @return list<string>
     */
    public function poluchitIdAdministratorov(): array;
}
