<?php

namespace App\Account\Business\ReadModel;

final class AccountSettingsReadModel
{
    public function __construct(
        public readonly ?string $nickname = null
    )
    {
    }
}
