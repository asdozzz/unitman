<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

use App\Runner\Business\Model\OpenCode\RekvizityOpenCode;

/**
 * Контракт бизнес-слоя Runner: получить реквизиты OpenCode для хода.
 *
 * Реализация лежит в `Acl` (адаптер над AccountApi), потому что модуль Runner
 * не имеет права обращаться к внутренним классам Account напрямую.
 */
interface UmeetPoluchatRekvizityOpenCode
{
    /**
     * Реквизиты с расшифрованным ключом, принадлежащие указанному аккаунту.
     *
     * `$model` — предпочтительная модель хода; `null` означает «дефолтная
     * модель реквизитов». Возвращаемая модель уже разрешена AccountApi.
     *
     * @throws \DomainException если реквизиты не найдены или принадлежат другому аккаунту
     */
    public function poluchitRekvizity(
        string $accountId,
        string $credentialId,
        ?string $model = null,
    ): RekvizityOpenCode;
}
