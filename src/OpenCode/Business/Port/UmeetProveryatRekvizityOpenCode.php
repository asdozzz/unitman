<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\ReadModel\RekvizityOpenCodeModeliReadModel;

/**
 * Доступ OpenCode к реквизитам подключения, которыми владеет Account.
 *
 * Модуль OpenCode **не получает API-ключ**: он только проверяет владение,
 * выбирает модель, а расшифровывает и передаёт ключ раннеру модуль Runner.
 */
interface UmeetProveryatRekvizityOpenCode
{
    /**
     * Идентификатор текущего пользователя из JWT.
     */
    public function tekuschiiAccountId(): string;

    /**
     * @throws \DomainException если реквизиты не найдены или принадлежат другому аккаунту
     */
    public function trebovatVladelca(string $accountId, string $credentialId): void;

    /**
     * Дефолтная модель и список моделей реквизитов.
     *
     * @throws \DomainException если реквизиты не найдены или принадлежат другому аккаунту
     */
    public function poluchitModeli(string $accountId, string $credentialId): RekvizityOpenCodeModeliReadModel;

    /**
     * Разрешает модель хода: пустая предпочтительная модель -> дефолт,
     * непустая обязана входить в набор моделей реквизитов.
     *
     * @throws \DomainException `account.opencode_credential.model_not_available`
     */
    public function razreshitModel(string $accountId, string $credentialId, ?string $preferred): string;
}
