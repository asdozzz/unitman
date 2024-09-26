<?php

namespace App\Unitman\Business\Model;

readonly class Account
{
    public function __construct(public string $id, public string $email, public bool $isSystemRole = false)
    {
    }

}
