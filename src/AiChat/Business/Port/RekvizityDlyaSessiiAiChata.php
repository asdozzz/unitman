<?php

declare(strict_types=1);

namespace App\AiChat\Business\Port;

use App\Account\Business\ReadModel\OpenCodeCredentialSecretReadModel;
use App\Account\Business\ReadModel\ProviderSessionPolicyReadModel;

/**
 * Узкий порт доступа AI-чата к аккаунту и его реквизитам OpenCode.
 *
 * `AccountApi` — `final`-фасад модуля Account, поэтому для use case введён
 * собственный порт нужной ширины: он же служит швом для in-memory фейков в
 * тестах. Реализация — Acl-адаптер над `AccountApi` в инфраструктуре.
 */
interface RekvizityDlyaSessiiAiChata
{
    /**
     * Идентификатор текущего аутентифицированного пользователя.
     *
     * @throws \DomainException `account.not_authenticated`
     */
    public function tekuschiiAccountId(): string;

    /**
     * Идентификаторы всех существующих реквизитов аккаунта.
     *
     * @return list<string>
     */
    public function identifikatoryRekvizitov(string $accountId): array;

    /**
     * Реквизиты OpenCode владельца с разрешённой моделью.
     *
     * @throws \DomainException если реквизиты не найдены, чужие или модель недоступна
     */
    public function poluchitRekvizity(
        string $accountId,
        string $credentialId,
        ?string $model,
    ): OpenCodeCredentialSecretReadModel;

    /**
     * Политика provider-сессии для провайдера.
     *
     * @throws \DomainException если провайдер пуст или неизвестен
     */
    public function poluchitPolitiku(string $provider): ProviderSessionPolicyReadModel;
}
