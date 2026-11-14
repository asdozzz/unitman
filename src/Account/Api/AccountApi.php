<?php

namespace App\Account\Api;

use App\Account\Business\Model\JWTUser;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\UmeetShifrovatSekrety;
use App\Account\Business\ReadModel\OpenCodeCredentialSecretReadModel;
use App\Account\Infra\Adapter\SymfonySecurityService;
use App\Account\Infra\Repository\JWTUserRepository;

final class AccountApi
{
    public function __construct(
        private SymfonySecurityService $securityService,
        private JWTUserRepository $userRepository,
        private OpenCodeCredentialRepository $openCodeCredentialRepository,
        private UmeetShifrovatSekrety $umeetShifrovatSekrety,
    ) {
    }

    public function isAdmin(): bool
    {
        return $this->securityService->isAdmin();
    }

    public function getCurrentUserId(): string
    {
        return $this->securityService->getCurrentUserId();
    }

    public function getEmailOrNicknameByUserId(string $id): string
    {
        $user = $this->userRepository->getById($id);
        $nickname = $user->getNickname();
        return !empty($nickname) ? $nickname : $user->getEmail();
    }

    public function getCurrentUser(): JWTUser
    {
        $id = $this->securityService->getCurrentUserId();
        $user = $this->userRepository->getById($id);
        return $user;
    }

    public function getUserById(string $userId): JWTUser
    {
        $user = $this->userRepository->getById($userId);
        return $user;
    }

    public function getSystemUser(): JWTUser
    {
        return $this->userRepository->getSystemAccount();
    }

    /**
     * Реквизиты OpenCode с расшифрованным ключом для конкретного владельца.
     *
     * Единственная точка, где ключ покидает модуль Account в открытом виде.
     * Вызывается только из бэкенда (Acl-адаптер Runner/OpenCode) и никогда
     * не отдаётся в HTTP-ответы. Чужие реквизиты недоступны: владение
     * проверяется до расшифровки.
     *
     * @throws \DomainException если реквизиты не найдены или принадлежат другому аккаунту
     */
    public function poluchitRekvizityOpenCodeDlyVladelca(
        string $accountId,
        string $credentialId,
    ): OpenCodeCredentialSecretReadModel {
        $credential = $this->openCodeCredentialRepository->getById($credentialId);
        $credential->trebovatVladelca($accountId);

        return new OpenCodeCredentialSecretReadModel(
            $credential->getId(),
            $credential->getAccountId(),
            $credential->getUrl(),
            $credential->getModel(),
            $this->umeetShifrovatSekrety->rasshifrovat(
                $credential->getApiKeyCipher(),
                OpenCodeCredential::makeAad($credential->getAccountId(), $credential->getId()),
            ),
        );
    }

    /**
     * Проверка владения реквизитами OpenCode **без расшифровки** ключа.
     *
     * Нужна модулю OpenCode перед стартом хода: ему достаточно знать, что
     * реквизиты свои, а сам ключ забирает Runner в момент запуска активити.
     * Так открытый ключ не проходит через модуль, который его не использует.
     *
     * @throws \DomainException если реквизиты не найдены или принадлежат другому аккаунту
     */
    public function trebovatRekvizityOpenCodeVladelca(string $accountId, string $credentialId): void
    {
        $credential = $this->openCodeCredentialRepository->getById($credentialId);
        $credential->trebovatVladelca($accountId);
    }
}
