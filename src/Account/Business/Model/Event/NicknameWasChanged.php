<?php

namespace App\Account\Business\Model\Event;

final class NicknameWasChanged
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $newNickname
    )
    {
    }
}
