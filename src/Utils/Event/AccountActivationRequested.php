<?php

declare(strict_types=1);

namespace App\Utils\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Интеграционное событие: пользователь зарегистрировался и ожидает активации.
 *
 * Живёт в общем слое Utils, потому что Account не имеет права зависеть от
 * Notification, а Notification — слушать внутренние классы Account. Такой
 * контракт позволяет модулю уведомлений подписаться на факт регистрации, не
 * нарушая границы модулей.
 */
final class AccountActivationRequested extends Event
{
    public const NAME = 'account.activation_requested';

    public function __construct(
        public readonly string $accountId,
        public readonly string $email,
        public readonly ?string $nickname,
    ) {
    }
}
