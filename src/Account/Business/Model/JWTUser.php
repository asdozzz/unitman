<?php

namespace App\Account\Business\Model;

use App\Account\Business\Model\Account\Role;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class JWTUser implements PasswordAuthenticatedUserInterface, JWTUserInterface
{
    public function __construct(
        private string $id,
        private string $email,
        private string $password,
        private array $roles,
        private bool $isBlocked = false,
        private string $locale = 'ru',
        private ?string $nickname = null
    )
    {
    }

    public static function createFromPayload($username, array $payload)
    {
        return new self(
            $payload['id'],
            $username,
            $payload['password'],
            $payload['roles'],
            $payload['isBlocked'],
            $payload['nickname'] ?? ""
        );
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function updatePassword(string $newPassword): void
    {
        $this->password = $newPassword;
    }

    public function updateRole(string $newRole): void
    {
        $this->roles = [$newRole];
    }

    public function updateEmail(string $newEmail): void
    {
        $this->email = $newEmail;
    }

    public function block(): void
    {
        $this->isBlocked = true;
    }

    public function unblock(): void
    {
        $this->isBlocked = false;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
       return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return bool
     */
    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function hasSystemRole(): bool
    {
        return in_array(Role::ROLE_SYSTEM->value, $this->roles);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $newLocale): void
    {
        $this->locale = $newLocale;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): void
    {
        $this->nickname = $nickname;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // TODO: Implement eraseCredentials() method.
    }
}
