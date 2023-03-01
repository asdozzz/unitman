<?php

namespace App\Account\Business\Model\Account;

final class Email implements \Stringable
{
    private string $email;

    public function __construct(string $email)
    {
        if (empty($email)) {
            throw new \Exception('email.is.empty');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('email.invalid');
        }
        $this->email = $email;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
