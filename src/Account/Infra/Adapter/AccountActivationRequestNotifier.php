<?php

declare(strict_types=1);

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\UmeetOpoveshatOZaproseAktivacii;
use App\Utils\Event\AccountActivationRequested;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Публикует интеграционное событие о заявке на активацию.
 *
 * Account не зависит от Notification напрямую: событие слушает модуль
 * уведомлений в своём Infra-слое.
 */
final class AccountActivationRequestNotifier implements UmeetOpoveshatOZaproseAktivacii
{
    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function zaproshena(string $accountId, string $email, ?string $nickname): void
    {
        $this->dispatcher->dispatch(
            new AccountActivationRequested($accountId, $email, $nickname),
            AccountActivationRequested::NAME,
        );
    }
}
