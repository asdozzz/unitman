<?php

declare(strict_types=1);

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Публичная заявка на регистрацию.
 *
 * Роль не передаётся клиентом: самостоятельно зарегистрированный аккаунт
 * всегда получает ROLE_PENDING до решения администратора.
 */
final class RegisterPendingAccount implements JsonBodySerializableInterface
{
    public function __construct(
        private string $email,
        private string $password,
        private string $locale = 'ru',
        public readonly ?string $nickname = null,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
