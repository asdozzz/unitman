<?php

namespace App\Account\Business\Model;

use App\Account\Business\Command\ChangeMyLocale;
use App\Account\Business\Command\RegisterPendingAccount;
use App\Account\Business\Model\Account\AccountId;
use App\Account\Business\Model\Account\Email;
use App\Account\Business\Model\Account\Nickname;
use App\Account\Business\Model\Account\Password;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Model\Event\AccountEmailVerified;
use App\Account\Business\Model\Event\AccountRegistrationRequested;
use App\Account\Business\Model\Event\AccountWasActivatedByAdmin;
use App\Account\Business\Model\Event\AccountWasRegisteredViaOAuth;
use App\Account\Business\Model\Event\AccountWasBlockedByAdmin;
use App\Account\Business\Model\Event\AccountWasRegistered;
use App\Account\Business\Model\Event\AccountWasUnblockedByAdmin;
use App\Account\Business\Model\Event\EmailWasChangedByAdmin;
use App\Account\Business\Model\Event\LocaleChanged;
use App\Account\Business\Model\Event\NicknameWasChanged;
use App\Account\Business\Model\Event\NicknameWasChangedByAdmin;
use App\Account\Business\Model\Event\PasswordWasChanged;
use App\Account\Business\Model\Event\PasswordWasChangedByAdmin;
use App\Account\Business\Model\Event\RoleWasChangedByAdmin;
use App\Account\Business\Model\Event\SystemAccountWasRegistered;
use App\Utils\Exception\TranslatorKeyword;
use App\Utils\Model\Locale\SupportedLocale;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

/**
 * @template-implements AggregateRoot<AccountId>
 * */
final class Account implements AggregateRoot
{
    /**
     * @template-use AggregateRootBehaviour<AccountId>
     * */
    use AggregateRootBehaviour;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private AccountId $accountId;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private Email $email;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private Password $password;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private Role $role;

    private bool $isBlocked = false;

    /**
     * Аккаунт зарегистрировался самостоятельно и ещё не активирован
     * администратором.
     */
    private bool $isPendingActivation = false;

    /**
     * Email подтверждён кодом из письма или OAuth-провайдером.
     */
    private bool $isEmailVerified = false;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private string $locale;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private Nickname $nickname;

    public static function registerActiveAccount(
        string $accountId,
        string $email,
        string $passwordHash,
        string $role,
        string $locale,
        string $nickname = '',
    ): static {
        if ($role === Account\Role::ROLE_SYSTEM->value) {
            throw new \DomainException(TranslatorKeyword::SYSTEM_CANNOT_ADD_USERS->value);
        }

        $account = new static(AccountId::fromString($accountId));
        $account->recordThat(new AccountWasRegistered($accountId, $email, $passwordHash, $role, $locale, $nickname));
        return $account;
    }

    /**
     * Самостоятельная регистрация: аккаунт создаётся сразу, но остаётся
     * неактивированным до решения администратора.
     */
    public static function requestRegistration(string $accountId, RegisterPendingAccount $command): static
    {
        $account = new static(AccountId::fromString($accountId));
        $account->recordThat(new AccountRegistrationRequested(
            $accountId,
            $command->getEmail(),
            $command->getPassword(),
            Role::ROLE_PENDING->value,
            $command->getLocale(),
            $command->nickname ?? '',
        ));

        return $account;
    }

    /**
     * Аккаунт, созданный через Google/GitHub: email уже подтверждён
     * провайдером, но аккаунт ещё должен активировать администратор.
     */
    public static function registerOauthAccount(
        string $accountId,
        string $email,
        string $passwordHash,
        string $locale,
        string $nickname = '',
    ): static {
        $account = new static(AccountId::fromString($accountId));
        $account->recordThat(new AccountWasRegisteredViaOAuth(
            $accountId,
            $email,
            $passwordHash,
            $locale,
            $nickname,
        ));

        return $account;
    }

    private function applyAccountWasRegistered(AccountWasRegistered $fact): void
    {
        $this->accountId = AccountId::fromString($fact->accountId);
        $this->email = new Email($fact->email);
        $this->password = new Password($fact->password);
        $this->role = Role::from($fact->role);
        $this->locale = $fact->locale;
        $this->nickname = new Nickname($fact->nickname);
        $this->isPendingActivation = false;
        $this->isEmailVerified = true;
    }

    private function applyAccountRegistrationRequested(AccountRegistrationRequested $fact): void
    {
        $this->accountId = AccountId::fromString($fact->accountId);
        $this->email = new Email($fact->email);
        $this->password = new Password($fact->password);
        $this->role = Role::from($fact->role);
        $this->locale = $fact->locale;
        $this->nickname = new Nickname($fact->nickname);
        $this->isPendingActivation = true;
        $this->isEmailVerified = false;
    }

    private function applyAccountWasRegisteredViaOAuth(AccountWasRegisteredViaOAuth $fact): void
    {
        $this->accountId = AccountId::fromString($fact->accountId);
        $this->email = new Email($fact->email);
        $this->password = new Password($fact->password);
        $this->role = Role::ROLE_PENDING;
        $this->locale = $fact->locale;
        $this->nickname = new Nickname($fact->nickname);
        $this->isEmailVerified = true;
        $this->isPendingActivation = true;
    }

    public static function registerSystemAccount(string $accountId, string $password, string $locale): static
    {
        $account = new static(AccountId::fromString($accountId));
        $account->recordThat(new SystemAccountWasRegistered($accountId, 'system@system.com', $password, Role::ROLE_SYSTEM->value, $locale));
        return $account;
    }

    private function applySystemAccountWasRegistered(SystemAccountWasRegistered $fact): void
    {
        $this->accountId = AccountId::fromString($fact->accountId);
        $this->email = new Email($fact->email);
        $this->password = new Password($fact->password);
        $this->role = Role::from($fact->role);
        $this->locale = $fact->locale;
        $this->isEmailVerified = true;
    }

    public function changeMyLocale(ChangeMyLocale $command): void
    {
        if (!SupportedLocale::isSupported($command->locale)) {
            throw new \DomainException('account.locale_not_supported');
        }

        if ($this->locale === $command->locale) {
            throw new \DomainException('account.old_locale_equal_new_locale');
        }

        $this->recordThat(new LocaleChanged($this->accountId->toString(), $command->locale));
    }

    private function applyLocaleChanged(LocaleChanged $fact): void
    {
        $this->locale = $fact->newLocale;
    }

    public function changePasswordByAdmin(string $newPassword): void
    {
        if ($this->password->__toString() === $newPassword) {
            throw new \DomainException('account.old_password_equal_new_password');
        }

        $this->checkSystemRole();

        $this->recordThat(new PasswordWasChangedByAdmin($this->accountId->toString(), $newPassword));
    }

    private function applyPasswordWasChangedByAdmin(PasswordWasChangedByAdmin $fact): void
    {
        $this->password = new Password($fact->newPassword);
    }


    public function changeMyPassword(string $newPassword): void
    {
        $this->recordThat(new PasswordWasChanged($this->accountId->toString(), $newPassword));
    }

    private function applyPasswordWasChanged(PasswordWasChanged $fact): void
    {
        $this->password = new Password($fact->newPassword);
    }

    public function changeMyNickname(string $newNickname): void
    {
        Nickname::validateNewNickname($newNickname);

        if ($this->nickname->__toString() === $newNickname) {
            throw new \DomainException('account.old_nickname_equal_new_nickname');
        }

        $this->recordThat(new NicknameWasChanged($this->accountId->toString(), $newNickname));
    }

    private function applyNicknameWasChanged(NicknameWasChanged $fact): void
    {
        $this->nickname = new Nickname($fact->newNickname);
    }

    public function changeEmailByAdmin(string $newEmail): void
    {
        if ($this->getEmail() === $newEmail) {
            throw new \RuntimeException('account.old_email_equal_new_email');
        }

        $this->checkSystemRole();

        $this->recordThat(new EmailWasChangedByAdmin($this->accountId->toString(), $newEmail));
    }

    private function applyEmailWasChangedByAdmin(EmailWasChangedByAdmin $fact): void
    {
        $this->email = new Email($fact->newEmail);
    }

    public function changeNicknameByAdmin(string $newNickname): void
    {
        $this->checkSystemRole();

        Nickname::validateNewNickname($newNickname);

        if ((string)$this->nickname === $newNickname) {
            throw new \RuntimeException('account.old_nickname_equal_new_nickname');
        }

        $this->recordThat(new NicknameWasChangedByAdmin($this->accountId->toString(), $newNickname));
    }

    private function applyNicknameWasChangedByAdmin(NicknameWasChangedByAdmin $fact): void
    {
        $this->nickname = new Nickname($fact->newNickname);
    }

    public function changeRoleByAdmin(string $newRole): void
    {
        $this->checkSystemRole();

        if ($this->role->value === $newRole) {
            throw new \RuntimeException('account.old_role_equal_new_role');
        }

        $this->recordThat(new RoleWasChangedByAdmin($this->accountId->toString(), $newRole));
    }

    private function applyRoleWasChangedByAdmin(RoleWasChangedByAdmin $fact): void
    {
        $this->role = Role::from($fact->newRole);
    }

    /**
     * Подтверждение email после ввода кода из письма.
     */
    public function confirmEmail(): void
    {
        if ($this->isEmailVerified) {
            throw new \DomainException('account.email.already_verified');
        }

        $this->recordThat(new AccountEmailVerified(
            $this->accountId->toString(),
            (new \DateTimeImmutable())->format(DATE_ATOM),
        ));
    }

    private function applyAccountEmailVerified(AccountEmailVerified $fact): void
    {
        $this->isEmailVerified = true;
    }

    /**
     * Активация самостоятельно зарегистрированного аккаунта администратором.
     */
    public function activateByAdmin(): void
    {
        if (!$this->isEmailVerified) {
            throw new \DomainException('account.email.not_verified');
        }

        if (!$this->isPendingActivation) {
            throw new \DomainException('account.not_pending_activation');
        }

        $this->recordThat(new AccountWasActivatedByAdmin($this->accountId->toString(), Role::ROLE_USER->value));
    }

    private function applyAccountWasActivatedByAdmin(AccountWasActivatedByAdmin $fact): void
    {
        $this->role = Role::from($fact->newRole);
        $this->isPendingActivation = false;
    }

    public function isPendingActivation(): bool
    {
        return $this->isPendingActivation;
    }

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function getId(): string
    {
        return $this->accountId->toString();
    }

    public function blockByAdmin(?string $reason): void
    {
        if ($this->isBlocked) {
            throw new \DomainException('account.already_blocked');
        }
        $this->checkSystemRole();
        $this->recordThat(new AccountWasBlockedByAdmin($this->accountId->toString(), $reason));
    }

    private function applyAccountWasBlockedByAdmin(AccountWasBlockedByAdmin $fact): void
    {
        $this->isBlocked = true;
    }

    public function unblockByAdmin(?string $reason): void
    {
        if (!$this->isBlocked) {
            throw new \DomainException('account.is_not_blocked');
        }
        $this->checkSystemRole();
        $this->recordThat(new AccountWasUnblockedByAdmin($this->accountId->toString(), $reason));
    }

    private function applyAccountWasUnblockedByAdmin(AccountWasUnblockedByAdmin $fact): void
    {
        $this->isBlocked = false;
    }

    public function getEmail(): string
    {
        return (string) $this->email;
    }

    public function getRoleValue(): string
    {
        return $this->role->value;
    }

    /**
     * @return void
     */
    private function checkSystemRole(): void
    {
        if ($this->role === Account\Role::ROLE_SYSTEM) {
            throw new \DomainException('account.as_system_not_allowed');
        }
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getNickname(): string
    {
        return (string) $this->nickname;
    }
}
