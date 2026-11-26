<?php

declare(strict_types=1);

namespace App\Notification\Business\UseCase;

use App\Notification\Business\Command\SozdatUvedomlenie;
use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Model\UrovenVazhnosti;
use App\Notification\Business\Port\UmeetPoluchatAdministratorov;

/**
 * Уведомление администраторов о заявке на активацию аккаунта.
 *
 * Вынесено в use case, чтобы и публичный API модуля, и слушатель
 * интеграционного события использовали один и тот же сценарий, не создавая
 * зависимость Infra → Api.
 */
final class SozdatUvedomlenieOZaproseAktivaciiUseCase
{
    public function __construct(
        private readonly SozdatUvedomlenieUseCase $sozdatUvedomlenie,
        private readonly UmeetPoluchatAdministratorov $administratory,
    ) {
    }

    public function handle(string $accountId, string $email, ?string $nickname): void
    {
        $this->sozdatUvedomlenie->handle(new SozdatUvedomlenie(
            TipUvedomleniya::ACCOUNT_ACTIVATION_REQUEST,
            UrovenVazhnosti::ATTENTION,
            $this->administratory->poluchitIdAdministratorov(),
            [
                'accountId' => $accountId,
                'email' => $email,
                'nickname' => $nickname,
            ],
            'account_activation_request:' . $accountId,
        ));
    }
}
