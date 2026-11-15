<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\BackgroundJob\SinhronizaciyaSessiySUnitom;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use Psr\Log\LoggerInterface;

/**
 * Сверка активных сессий с состоянием их юнитов.
 *
 * Модель «pull»: юнит ничего не знает о сессиях, поэтому соответствие
 * поддерживается опросом из модуля OpenCode. Для каждой активной сессии
 * обходятся ВСЕ её юниты:
 *
 * - юнит удалён (`archived`) — пропускается; сессия архивируется с причиной
 *   `unit_deleted` только когда удалены все её юниты;
 * - юнит ещё в конвейере (`blocked`) — цикл продвигается, чтобы сборка
 *   не застревала, если пользователь не открывает страницу юнита;
 * - юнит готов (`ready`) — ничего не делаем.
 */
final class SinhronizaciyaSessiySUnitomBackgroundJob extends AbstractBackgroundJob
{
    private const BATCH_LIMIT = 200;

    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetUpravlyatUnitami $unitami,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'opencode_session_unit_sync';
    }

    public function getDelay(): int
    {
        return 5;
    }

    public function getProcessNum(): int
    {
        return 1;
    }

    public function run(): bool
    {
        try {
            $sessions = $this->sessionRepository->findAktivnieSUnitom(self::BATCH_LIMIT);
        } catch (\Throwable $e) {
            $this->logger->error('opencode.session_sync.batch_load_failed', [
                'exception' => $e,
            ]);

            return true;
        }

        foreach ($sessions as $session) {
            $this->obrabotatSessiyu($session);
        }

        return true;
    }

    private function obrabotatSessiyu(SessiyaOpenCode $session): void
    {
        if ($session->estArhiv() || $session->getUnits() === []) {
            return;
        }

        $estZhivoi = false;

        foreach ($session->getUnits() as $privyazka) {
            try {
                $sostoyanie = $this->unitami->poluchitSostoyanieUnita($privyazka->unitId);
            } catch (\Throwable $e) {
                // Ошибка одного юнита не прерывает проход по пачке и не мешает
                // обработать остальные юниты этой же сессии.
                $this->logger->error('opencode.session_sync.unit_failed', [
                    'sessionId' => $session->getId(),
                    'unitId' => $privyazka->unitId,
                    'exception' => $e,
                ]);

                continue;
            }

            if ($sostoyanie->group === SostoyanieUnita::GROUP_ARCHIVED) {
                continue;
            }

            $estZhivoi = true;

            if ($sostoyanie->group === SostoyanieUnita::GROUP_BLOCKED) {
                $this->prodvinut($session, $privyazka->unitId);
            }
        }

        if (!$estZhivoi) {
            $this->arhivirovat($session);
        }
    }

    private function arhivirovat(SessiyaOpenCode $session): void
    {
        $session->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_UNIT_DELETED, time());
        $this->sessionRepository->update($session);

        $this->logger->info('opencode.session_sync.session_archived', [
            'sessionId' => $session->getId(),
            'unitIds' => $session->getUnitIds(),
        ]);
    }

    private function prodvinut(SessiyaOpenCode $session, string $unitId): void
    {
        try {
            $this->unitami->prodvinutCiklUnita($unitId, $session->getAccountId());
        } catch (\Throwable $e) {
            $this->logger->error('opencode.session_sync.advance_failed', [
                'sessionId' => $session->getId(),
                'unitId' => $unitId,
                'exception' => $e,
            ]);
        }
    }
}
