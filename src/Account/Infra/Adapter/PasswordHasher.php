<?php

namespace App\Account\Infra\Adapter;

use App\Account\Business\Model\JWTUser;
use App\Account\Business\Port\CanHashPassword;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class PasswordHasher implements CanHashPassword
{
    public function __construct(
        private PasswordHasherFactoryInterface $passwordHasherFactory
    )
    {
    }

    public function hashPassword(string $password): string
    {
        $hasher = $this->passwordHasherFactory->getPasswordHasher(JWTUser::class);
        return $hasher->hash($password);
    }
}
