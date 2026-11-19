<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use Psr\Log\LoggerInterface;

/**
 * Единая точка завершения хода OpenCode.
 *
 * Пока ход в RUNNING, результат у раннера может отсутствовать — тогда
 * состояние не меняется. Как только результат получен, он фиксируется в БД,
 * внешняя сессия запоминается, юниты освобождаются, а при переходе в
 * терминальный статус создаётся уведомление.
 *
 * Уведомление сохраняется всегда, но публикуется только если вставка строки
 * действительно произошла: повторное завершение того же хода не создаёт дубль
 * и не публикует старое уведомление второй раз. Ошибка публикации не отменяет
 * завершение: непрочитанное уведомление останется в БД и будет отдано через API.
 */
final class ZavershenieHodaOpenCode
{
    public function __construct(
        private HodOpenCodeRepository $turnRepository,
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetVypolnyatHodOpenCode $runner,
        private UmeetBronirovatUniti $bronirovanie,
        private UvedomleniyaOpenCodeRepository $notifications,
        private UmeetPeredavatUvedomleniyaOpenCode $publisher,
        private UuidGenerator $uuidGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function zavershit(HodOpenCode $turn): HodOpenCode
    {
        if (!$turn->estVProzesse()) {
            return $turn;
        }

        $result = $this->runner->poluchitResultatHoda($turn->getWorkflowId());

        if ($result === null) {
            return $turn;
        }

        $session = $this->sessionRepository->getById($turn->getSessionId());
        $wasRunning = $turn->estVProzesse();
        $now = time();

        $turn->primenitRezultat($result, $now);
        $this->turnRepository->update($turn);
        $this->zapomnitVneshnyuyuSessiyu($session, $result, $now);

        if (!$wasRunning || $turn->estVProzesse()) {
            return $turn;
        }

        $this->osvoboditUniti($session, $turn);
        $this->sozdatUvedomlenie($turn, $session, $now);

        return $turn;
    }

    /**
     * Освобождаются все юниты сессии, а не только те, что попали в ход:
     * захват ставится по `SessiyaOpenCode::getUnits()`, и снимать его нужно
     * тем же набором. Ошибка освобождения не должна ломать завершение хода —
     * занятость истечёт по TTL.
     */
    private function osvoboditUniti(SessiyaOpenCode $session, HodOpenCode $turn): void
    {
        $unitIds = [];

        foreach ($session->getUnits() as $privyazka) {
            $unitIds[] = $privyazka->unitId;
        }

        if ($unitIds === []) {
            return;
        }

        try {
            $this->bronirovanie->osvobodit($unitIds, VladelecZanyatostiOpenCode::dlyaHoda($turn->getId()));
        } catch (\Throwable) {
            // Занятость истечёт по TTL; результат хода важнее.
        }
    }

    /**
     * Внешний идентификатор сессии OpenCode запоминается на сессии, чтобы
     * следующие ходы продолжили тот же диалог на стороне раннера.
     */
    private function zapomnitVneshnyuyuSessiyu(
        SessiyaOpenCode $session,
        RezultatHodaOpenCode $result,
        int $now,
    ): void {
        $externalSessionId = $result->externalSessionId;

        if ($externalSessionId === null || $externalSessionId === '') {
            return;
        }

        if ($session->getExternalSessionId() === $externalSessionId) {
            return;
        }

        $session->zapomnitVneshnyuyuSessiyu($externalSessionId, $now);
        $this->sessionRepository->update($session);
    }

    private function sozdatUvedomlenie(HodOpenCode $turn, SessiyaOpenCode $session, int $now): void
    {
        $notification = new UvedomlenieONovomSoobsheniiOpenCode(
            $this->uuidGenerator->makeGuid(),
            $session->getAccountId(),
            $turn->getSessionId(),
            $turn->getId(),
            $session->getTitle(),
            $turn->getStatus()->value,
            $turn->getResult()?->kind,
            $now,
        );

        try {
            $saved = $this->notifications->save($notification);
        } catch (\Throwable $error) {
            $this->logger->error('opencode.turn.notification_save_failed', [
                'turnId' => $turn->getId(),
                'exception' => $error,
            ]);

            return;
        }

        if (!$saved) {
            return;
        }

        try {
            $this->publisher->publish($notification);
        } catch (\Throwable $error) {
            // Публикация — best effort: уведомление уже сохранено и будет
            // отдано при следующем старте приложения или reconnect.
            $this->logger->error('opencode.turn.notification_publish_failed', [
                'notificationId' => $notification->id,
                'turnId' => $turn->getId(),
                'exception' => $error,
            ]);
        }
    }
}
