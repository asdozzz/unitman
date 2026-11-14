<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitProgressHoda;
use App\OpenCode\Business\Model\ProgressHodaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;

/**
 * Pull-опрос шагов, которые раннер уже прислал через saveSteps.
 *
 * Push-доставка идёт отдельным каналом Centrifugo, этот запрос нужен как
 * резервный путь: восстановить состояние после перезагрузки страницы или
 * если подписка на канал не установилась.
 */
final class PoluchitProgressHodaQuery
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
    public function handle(PoluchitProgressHoda $command): ?ProgressHodaOpenCode
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $turn = $this->turnRepository->getById($command->turnId);
        $session = $this->sessionRepository->getById($turn->getSessionId());

        $session->trebovatVladelca($accountId);

        return $this->runner->poluchitProgressHoda($turn->getWorkflowId());
    }
}
