<?php

namespace App\Account\Infra\Projection;

use App\Account\Business\Model\Account\Role;
use App\Account\Business\Model\Event\AccountEmailVerified;
use App\Account\Business\Model\Event\AccountRegistrationRequested;
use App\Account\Business\Model\Event\AccountWasActivatedByAdmin;
use App\Account\Business\Model\Event\AccountWasBlockedByAdmin;
use App\Account\Business\Model\Event\AccountWasRegistered;
use App\Account\Business\Model\Event\AccountWasRegisteredViaOAuth;
use App\Account\Business\Model\Event\AccountWasUnblockedByAdmin;
use App\Account\Business\Model\Event\EmailWasChangedByAdmin;
use App\Account\Business\Model\Event\LocaleChanged;
use App\Account\Business\Model\Event\NicknameWasChanged;
use App\Account\Business\Model\Event\NicknameWasChangedByAdmin;
use App\Account\Business\Model\Event\PasswordWasChanged;
use App\Account\Business\Model\Event\PasswordWasChangedByAdmin;
use App\Account\Business\Model\Event\RoleWasChangedByAdmin;
use App\Account\Business\Model\Event\SystemAccountWasRegistered;
use App\Account\Business\Model\JWTUser;
use App\Account\Business\Utils\AccountEventTypeEnum;
use App\Account\Infra\Repository\JWTUserRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

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

    function resetById(string $id): void
    {
        $this->JWTUserRepository->resetById($id);
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
        $user = new JWTUser(
            $event->accountId,
            $event->email,
            $event->password,
            [$event->role],
            locale: $event->locale,
            nickname: $event->nickname,
            isPendingActivation: false,
        );
        $this->JWTUserRepository->save($user);
    }

    public function handleAccountRegistrationRequested(AccountRegistrationRequested $event): void
    {
        $user = new JWTUser(
            $event->accountId,
            $event->email,
            $event->password,
            [$event->role],
            locale: $event->locale,
            nickname: $event->nickname,
            isPendingActivation: true,
            isEmailVerified: false,
        );
        $this->JWTUserRepository->save($user);
    }

    public function handleAccountWasRegisteredViaOAuth(AccountWasRegisteredViaOAuth $event): void
    {
        $user = new JWTUser(
            $event->accountId,
            $event->email,
            $event->password,
            [Role::ROLE_PENDING->value],
            locale: $event->locale,
            nickname: $event->nickname,
            isPendingActivation: true,
            isEmailVerified: true,
        );
        $this->JWTUserRepository->save($user);
    }

    public function handleAccountEmailVerified(AccountEmailVerified $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->confirmEmail();
        $this->JWTUserRepository->update($user);
    }

    public function handleAccountWasActivatedByAdmin(AccountWasActivatedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->activate();
        $this->JWTUserRepository->update($user);
    }

    public function handleSystemAccountWasRegistered(SystemAccountWasRegistered $event): void
    {
        $user = new JWTUser($event->accountId, $event->email, $event->password, [$event->role], locale: $event->locale);
        $this->JWTUserRepository->save($user);
    }

    public function handlePasswordWasChangedByAdmin(PasswordWasChangedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->updatePassword($event->newPassword);
        $this->JWTUserRepository->update($user);
    }

    public function handleRoleWasChangedByAdmin(RoleWasChangedByAdmin $event): void {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->updateRole($event->newRole);
        $this->JWTUserRepository->update($user);
    }

    public function handlePasswordWasChanged(PasswordWasChanged $event): void
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

    public function handleLocaleChanged(LocaleChanged $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->setLocale($event->newLocale);
        $this->JWTUserRepository->update($user);
    }

    public function handleNicknameWasChangedByAdmin(NicknameWasChangedByAdmin $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->setNickname($event->newNickname);
        $this->JWTUserRepository->update($user);
    }

    public function handleNicknameWasChanged(NicknameWasChanged $event): void
    {
        $user = $this->JWTUserRepository->getById($event->accountId);
        $user->setNickname($event->newNickname);
        $this->JWTUserRepository->update($user);
    }

    function isSyncProjection(): bool
    {
        return true;
    }
}
