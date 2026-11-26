<?php

declare(strict_types=1);

namespace App\Notification\Api;

use App\Notification\Business\Command\SozdatUvedomlenie;
use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Model\UrovenVazhnosti;
use App\Notification\Business\Port\UmeetPoluchatAdministratorov;
use App\Notification\Business\UseCase\SozdatUvedomlenieOZaproseAktivaciiUseCase;
use App\Notification\Business\UseCase\SozdatUvedomlenieUseCase;
use App\Notification\Business\Utils\UrovenVazhnostiIzUgrozy;

/**
 * Публичный API модуля Notification для других модулей.
 */
final class NotificationApi
{
    public function __construct(
        private readonly SozdatUvedomlenieUseCase $sozdatUvedomlenie,
        private readonly UmeetPoluchatAdministratorov $administratory,
        private readonly SozdatUvedomlenieOZaproseAktivaciiUseCase $activationRequest,
    ) {
    }

    /**
     * Уведомление администраторам об угрозе раннера.
     *
     * @param array<string, mixed> $payload
     */
    public function sozdatUvedomlenieOBugrozeRannera(
        string $runnerId,
        string $level,
        array $payload,
        string $dedupKey,
    ): void {
        $severity = UrovenVazhnostiIzUgrozy::dlyaUrovnyaRannera($level);

        if ($severity === null) {
            return;
        }

        $this->sozdatUvedomlenie->handle(new SozdatUvedomlenie(
            TipUvedomleniya::RUNNER_THREAT,
            $severity,
            $this->administratory->poluchitIdAdministratorov(),
            $payload + ['runnerId' => $runnerId, 'level' => $level],
            $dedupKey,
        ));
    }

    /**
     * Уведомление администраторам о заявке на активацию аккаунта.
     *
     * Тип системный: отключить его в настройках нельзя. Уведомление создаётся
     * один раз на аккаунт благодаря dedup-ключу.
     */
    public function sozdatUvedomlenieOZaproseAktivacii(
        string $accountId,
        string $email,
        ?string $nickname,
    ): void {
        $this->activationRequest->handle($accountId, $email, $nickname);
    }

    /**
     * Уведомление автору об успешном запуске юнита.
     *
     * @param array<string, mixed> $payload
     */
    public function sozdatUvedomlenieOZapuskeUnita(
        string $accountId,
        array $payload,
        string $dedupKey,
    ): void {
        $this->sozdatUvedomlenie->handle(new SozdatUvedomlenie(
            TipUvedomleniya::UNIT_STARTED,
            UrovenVazhnosti::INFO,
            [$accountId],
            $payload,
            $dedupKey,
        ));
    }
}
