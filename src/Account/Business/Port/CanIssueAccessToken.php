<?php

namespace App\Account\Business\Port;

/**
 * Умеет выпускать access-токен (JWT) для аккаунта.
 * */
interface CanIssueAccessToken
{
    public function issue(string $accountId): string;
}
