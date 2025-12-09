<?php

namespace App\Unitman\Business\Model;

class Account
{
    public function __construct(public readonly string $id, public readonly string $email, public readonly bool $isSystemRole)
    {
    }

}
