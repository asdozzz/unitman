<?php

namespace App\Account\Infra\Projection;

use App\Account\Business\Model\Event\AccountWasBlockedByAdmin;
use App\Account\Business\Model\Event\AccountWasRegistered;
use App\Account\Business\Model\Event\AccountWasUnblockedByAdmin;
use App\Account\Business\Model\Event\EmailWasChangedByAdmin;
use App\Account\Business\Model\Event\PasswordWasChangedByAdmin;
use App\Account\Business\Model\JWTUser;
use App\Account\Business\Utils\AccountEventTypeEnum;
use App\Account\Infra\Repository\JWTUserRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use EventSauce\EventSourcing\Message;

final class JWTUserProjectionForAccount extends AbstractProjection implements SyncProjectionForAccount
{
    public function __construct(private JWTUserRepository $JWTUserRepository)
    {
    }

    function getProjectionName(): string
    {
        return 'spisok_polzovatelei';
    }

    function reset(): void
    {
        $this->JWTUserRepository->truncate();
    }

    function init(): void
    {
        $this->JWTUserRepository->init();
    }

    function destroy(): void
    {
        $this->JWTUserRepository->destroy();
    }

    function getStreamName(): StreamName
    {
        return new StreamName(AccountEventTypeEnum::Account->value);
    }

    public function handleAccountWasRegistered(AccountWasRegistered $event): void
    {
        $user = new JWTUser($event->accountId, $event->email, $event->password, [$event->role]);
        $this->JWTUserRepository->save($user);
    }

    public function handlePasswordWasChangedByAdmin(PasswordWasChangedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->updatePassword($event->newPassword);
        $this->JWTUserRepository->update($user);
    }

    public function handleEmailWasChangedByAdmin(EmailWasChangedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->updateEmail($event->newEmail);
        $this->JWTUserRepository->update($user);
    }

    public function handleAccountWasBlockedByAdmin(AccountWasBlockedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->block();
        $this->JWTUserRepository->update($user);
    }

    public function handleAccountWasUnblockedByAdmin(AccountWasUnblockedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->unblock();
        $this->JWTUserRepository->update($user);
    }

    function isSyncProjection(): bool
    {
        return true;
    }
}
