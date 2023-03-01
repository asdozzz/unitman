<?php

namespace App\Account\Infra\Projection;

use App\Account\Business\Model\Event\AccountWasBlockedByAdmin;
use App\Account\Business\Model\Event\AccountWasRegistered;
use App\Account\Business\Model\Event\AccountWasUnblockedByAdmin;
use App\Account\Business\Model\Event\EmailWasChangedByAdmin;
use App\Account\Business\Model\Event\PasswordWasChangedByAdmin;
use App\Account\Business\Model\JWTUser;
use App\Account\Infra\Repository\JWTUserRepository;
use EventSauce\EventSourcing\Message;

final class JWTUserProjectionForAccount implements SyncProjectionForAccount
{
    public function __construct(private JWTUserRepository $JWTUserRepository)
    {
    }

    public function accountWasRegistered(AccountWasRegistered $event): void
    {
        $user = new JWTUser($event->accountId, $event->email, $event->password, [$event->role]);
        $this->JWTUserRepository->save($user);
    }

    public function passwordWasChanged(PasswordWasChangedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->updatePassword($event->newPassword);
        $this->JWTUserRepository->update($user);
    }

    public function emailWasChanged(EmailWasChangedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->updateEmail($event->newEmail);
        $this->JWTUserRepository->update($user);
    }

    public function accountWasBlocked(AccountWasBlockedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->block();
        $this->JWTUserRepository->update($user);
    }

    public function accountWasUnblocked(AccountWasUnblockedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->unblock();
        $this->JWTUserRepository->update($user);
    }

    public function handle(Message $message): void
    {
        $event = $message->payload();

        match ($event::class) {
            AccountWasRegistered::class => $this->accountWasRegistered($event),
            PasswordWasChangedByAdmin::class => $this->passwordWasChanged($event),
            EmailWasChangedByAdmin::class => $this->emailWasChanged($event),
            AccountWasBlockedByAdmin::class => $this->accountWasBlocked($event),
            AccountWasUnblockedByAdmin::class => $this->accountWasUnblocked($event),
            default => throw new \RuntimeException(sprintf('Handler for event=%s in %s not found', $event::class, __CLASS__))
        };
    }
}
