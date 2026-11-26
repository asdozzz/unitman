<?php

declare(strict_types=1);

namespace App\Notification\Infra\EventListener;

use App\Notification\Business\UseCase\SozdatUvedomlenieOZaproseAktivaciiUseCase;
use App\Utils\Event\AccountActivationRequested;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Создаёт уведомление администраторам о заявке на активацию.
 *
 * Ошибка уведомления не должна ломать регистрацию: пользователь уже создан,
 * а администраторы увидят заявку в списке аккаунтов в любом случае.
 */
#[AsEventListener(event: AccountActivationRequested::NAME)]
final class AccountActivationRequestedListener
{
    public function __construct(
        private readonly SozdatUvedomlenieOZaproseAktivaciiUseCase $useCase,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AccountActivationRequested $event): void
    {
        try {
            $this->useCase->handle(
                $event->accountId,
                $event->email,
                $event->nickname,
            );
        } catch (\Throwable $error) {
            $this->logger->error('Не удалось создать уведомление о заявке на активацию аккаунта.', [
                'accountId' => $event->accountId,
                'exception' => $error,
            ]);
        }
    }
}
