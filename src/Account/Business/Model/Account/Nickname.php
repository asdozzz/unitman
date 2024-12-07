<?php

namespace App\Account\Business\Model\Account;

final class Nickname implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    static function validateNewNickname(string $newValue): void
    {
        if (empty($newValue)) {
            throw new \DomainException('account.nickname_is_empty');
        }

        if (strlen($newValue) < 3) {
            throw new \DomainException('account.nickname_invalid');
        }
    }
}
