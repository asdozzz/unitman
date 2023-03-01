<?php

namespace App\Account\Business\Port;

use App\Account\Business\Model\Account;

interface AccountRepository
{
    public function getBy(string $accountId): Account;

    public function save(Account $account): void;
}
