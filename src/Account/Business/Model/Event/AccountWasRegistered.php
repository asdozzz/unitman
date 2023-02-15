<?php

namespace App\Account\Business\Model\Event;

final class AccountWasRegistered
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
