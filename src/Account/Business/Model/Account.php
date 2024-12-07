<?php

namespace App\Account\Business\Model;

use App\Account\Business\Command\ChangeMyLocale;
use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Model\Account\AccountId;
use App\Account\Business\Model\Account\Email;
use App\Account\Business\Model\Account\Nickname;
use App\Account\Business\Model\Account\Password;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Model\Event\AccountWasBlockedByAdmin;
use App\Account\Business\Model\Event\AccountWasRegistered;
use App\Account\Business\Model\Event\AccountWasUnblockedByAdmin;
use App\Account\Business\Model\Event\EmailWasChangedByAdmin;
use App\Account\Business\Model\Event\LocaleChanged;
use App\Account\Business\Model\Event\NicknameWasChanged;
use App\Account\Business\Model\Event\NicknameWasChangedByAdmin;
use App\Account\Business\Model\Event\PasswordWasChanged;
use App\Account\Business\Model\Event\PasswordWasChangedByAdmin;
use App\Account\Business\Model\Event\SystemAccountWasRegistered;
use App\Utils\Exception\TranslatorKeyword;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use Generator;

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
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private string $locale;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private Nickname $nickname;

    public static function registerAccount(string $accountId, RegisterAccount $command): static
    {
        if ($command->getRoles() === Account\Role::ROLE_SYSTEM->value) {
            throw new \DomainException(TranslatorKeyword::SYSTEM_CANNOT_ADD_USERS->value);
        }
        $account = new static(AccountId::fromString($accountId));
        $account->recordThat(new AccountWasRegistered($accountId, $command->getEmail(), $command->getPassword(), $command->getRoles(), $command->getLocale(), $command->nickname ?? ""));
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
    }

    public function changeMyLocale(ChangeMyLocale $command): void
    {
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
}
