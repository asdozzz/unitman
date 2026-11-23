<?php

declare(strict_types=1);

namespace App\Account\Api;

use App\Account\Business\Model\JWTUser;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\UmeetShifrovatSekrety;
use App\Account\Business\ReadModel\OpenCodeCredentialModelsReadModel;
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
     * Если `$model` не передан (или пуст) — возвращается дефолтная модель.
     * Если передан — он обязан входить в набор моделей реквизитов.
     *
     * @throws \DomainException если реквизиты не найдены, принадлежат другому
     *                          аккаунту или модель недоступна
     */
    public function poluchitRekvizityOpenCodeDlyVladelca(
        string $accountId,
        string $credentialId,
        ?string $model = null,
    ): OpenCodeCredentialSecretReadModel {
        $credential = $this->openCodeCredentialRepository->getById($credentialId);
        $credential->trebovatVladelca($accountId);

        $resolvedModel = $this->razreshitModel($credential, $model);

        return new OpenCodeCredentialSecretReadModel(
            $credential->getId(),
            $credential->getAccountId(),
            $credential->getProvider(),
            $credential->getUrl(),
            $credential->getModels(),
            $credential->getDefaultModel(),
            $resolvedModel,
            $this->umeetShifrovatSekrety->rasshifrovat(
                $credential->getApiKeyCipher(),
                OpenCodeCredential::makeAad($credential->getAccountId(), $credential->getId()),
            ),
        );
    }

    /**
     * Дефолтная модель и список моделей реквизитов для конкретного владельца.
     *
     * @throws \DomainException если реквизиты не найдены или принадлежат другому аккаунту
     */
    public function poluchitModeliRekvizitovOpenCodeDlyVladelca(
        string $accountId,
        string $credentialId,
    ): OpenCodeCredentialModelsReadModel {
        $credential = $this->openCodeCredentialRepository->getById($credentialId);
        $credential->trebovatVladelca($accountId);

        return new OpenCodeCredentialModelsReadModel(
            $credential->getDefaultModel(),
            $credential->getModels(),
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

    /**
     * @throws \DomainException `account.opencode_credential.model_not_available`
     */
    private function razreshitModel(OpenCodeCredential $credential, ?string $model): string
    {
        $model = $model !== null ? trim($model) : '';

        if ($model === '') {
            return $credential->getDefaultModel();
        }

        if (!\in_array($model, $credential->getModels(), true)) {
            throw new \DomainException('account.opencode_credential.model_not_available');
        }

        return $model;
    }
}
