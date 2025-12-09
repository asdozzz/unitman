<?php

namespace App\Account\Business\Model\Event;

final class RoleWasChangedByAdmin
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $newRole
    )
    {
    }
}
