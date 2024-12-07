<?php

namespace App\Account\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

final class RegisterAccount implements JsonBodySerializableInterface
{
    public function __construct(
        private string $email,
        private string $password,
        private string $roles,
        private string $locale,
        public readonly ?string $nickname = null,
    )
    {
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }


    /**
     * @return string
     */
    public function getRoles(): string
    {
        return $this->roles;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }


}
