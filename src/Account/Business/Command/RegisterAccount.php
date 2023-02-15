<?php

namespace App\Account\Business\Command;

final class RegisterAccount
{
    public function __construct(private string $accountId, private string $email)
    {
    }

    /**
     * @return string
     */
    public function getAccountId(): string
    {
        return $this->accountId;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }


}
