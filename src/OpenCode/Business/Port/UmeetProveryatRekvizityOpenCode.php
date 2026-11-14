<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

/**
 * Доступ OpenCode к реквизитам подключения, которыми владеет Account.
 *
 * Модуль OpenCode **не получает API-ключ**: он только проверяет владение,
 * а расшифровывает и передаёт ключ раннеру модуль Runner.
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
}