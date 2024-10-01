<?php

namespace App\Account\Business\Model\Event;

final class LocaleChanged
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $newLocale
    )
    {
    }
}
