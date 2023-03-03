<?php

namespace App\Account\Business\Model;

use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Model\Account\AccountId;
use App\Account\Business\Model\Account\Email;
use App\Account\Business\Model\Account\Password;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Model\Event\AccountWasBlockedByAdmin;
use App\Account\Business\Model\Event\AccountWasRegistered;
use App\Account\Business\Model\Event\AccountWasUnblockedByAdmin;
use App\Account\Business\Model\Event\EmailWasChangedByAdmin;
use App\Account\Business\Model\Event\PasswordWasChangedByAdmin;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use Generator;

final class Account implements AggregateRoot
{
    /**
     * @template-use AggregateRootBehaviour<AccountId>
     * */
    use AggregateRootBehaviour;

    private ?AccountId $accountId = null;

    private ?Email $email = null;

    private ?Password $password = null;

    private ?Role $role = null;

    private $isBlocked = false;

    public static function registerAccount(string $accountId, RegisterAccount $command): static
    {
        $account = new static(AccountId::fromString($accountId));
        $account->recordThat(new AccountWasRegistered($accountId, $command->getEmail(), $command->getPassword(), $command->getRoles()));
        return $account;
    }

    private function applyAccountWasRegistered(AccountWasRegistered $fact): void
    {
        $this->accountId = AccountId::fromString($fact->accountId);
        $this->email = new Email($fact->email);
        $this->password = new Password($fact->password);
        $this->role = Role::from($fact->role);
    }

    public function changePasswordByAdmin(string $newPassword): void
    {
        if ($this->password->__toString() === $newPassword) {
            throw new \RuntimeException('account.old_password_equal_new_password');
        }

        $this->recordThat(new PasswordWasChangedByAdmin($this->accountId->toString(), $newPassword));
    }

    private function applyPasswordWasChangedByAdmin(PasswordWasChangedByAdmin $fact): void
    {
        $this->password = new Password($fact->newPassword);
    }

    public function changeEmailByAdmin(string $newEmail): void
    {
        if ($this->getEmail() === $newEmail) {
            throw new \RuntimeException('account.old_email_equal_new_email');
        }

        $this->recordThat(new EmailWasChangedByAdmin($this->accountId->toString(), $newEmail));
    }

    private function applyEmailWasChangedByAdmin(EmailWasChangedByAdmin $fact): void
    {
        $this->email = new Email($fact->newEmail);
    }

    public function blockByAdmin(?string $reason): void
    {
        if ($this->isBlocked) {
            throw new \DomainException('account.already_blocked');
        }
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
        $this->recordThat(new AccountWasUnblockedByAdmin($this->accountId->toString(), $reason));
    }

    private function applyAccountWasUnblockedByAdmin(AccountWasUnblockedByAdmin $fact): void
    {
        $this->isBlocked = false;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
