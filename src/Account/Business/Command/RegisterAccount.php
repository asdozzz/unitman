<?php

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Добавление аккаунта администратором.
 *
 * Пароль не передаётся клиентом: система генерирует временный пароль и
 * отправляет его пользователю письмом.
 */
final class RegisterAccount implements JsonBodySerializableInterface
{
    public function __construct(
        private string $email,
        private string $roles,
        private string $locale,
        public readonly ?string $nickname = null,
    )
    {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoles(): string
    {
        return $this->roles;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
