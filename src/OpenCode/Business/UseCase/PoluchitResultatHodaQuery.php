<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitResultatHoda;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;

/**
 * Опрос результата хода — единственный способ узнать, чем закончилась работа.
 *
 * Пока ход в статусе RUNNING, результата у раннера может не быть: тогда
 * отдаётся сохранённое состояние, и клиент опрашивает снова. Как только
 * результат получен, он фиксируется в базе и повторные опросы в Temporal
 * уже не ходят.
 */
final class PoluchitResultatHodaQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private HodOpenCodeRepository $turnRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
    ) {
    }

    /**
     * @throws \DomainException если ход не найден или принадлежит чужой сессии
     */
    public function handle(PoluchitResultatHoda $command): HodOpenCodeReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $turn = $this->turnRepository->getById($command->turnId);
        $session = $this->sessionRepository->getById($turn->getSessionId());

        $session->trebovatVladelca($accountId);

        if ($turn->estVProzesse()) {
            $result = $this->runner->poluchitResultatHoda($turn->getWorkflowId());

            if ($result !== null) {
                $now = time();

                $turn->primenitRezultat($result, $now);
                $this->turnRepository->update($turn);
                $this->zapomnitVneshnyuyuSessiyu($session, $result, $now);
            }
        }

        return HodOpenCodeReadModel::izModela(
            $turn,
            $this->runner->kanalProgressa($turn->getWorkflowId()),
        );
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
}
