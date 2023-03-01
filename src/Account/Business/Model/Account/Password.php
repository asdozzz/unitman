<?php

namespace App\Account\Business\Model\Account;

final class Password implements \Stringable
{
    private string $password;

    public function __construct(string $password)
    {
        if (empty($password)) {
            throw new \Exception('password.empty');
        }
        $this->password = $password;
    }

    public function __toString(): string
    {
        return $this->password;
    }
}
