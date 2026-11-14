<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\NachatPrompt;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\RezultatZapuskaHoda;
use App\OpenCode\Business\Utils\ZapuskatelHodaOpenCode;

/**
 * Новый промпт в существующей сессии.
 *
 * Промпт отклоняется, если предыдущий ход ждёт ответа на вопрос: продолжение
 * диалога в этом случае возможно только через [`OtvetitNaVoprosUseCase`](src/OpenCode/Business/UseCase/OtvetitNaVoprosUseCase.php).
 */
final class NachatPromptUseCase
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private HodOpenCodeRepository $turnRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private ZapuskatelHodaOpenCode $launcher,
        private UmeetVypolnyatHodOpenCode $runner,
    ) {
    }

    public function handle(NachatPrompt $command): RezultatZapuskaHoda
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $session = $this->sessionRepository->getById($command->sessionId);
        $session->trebovatVladelca($accountId);

        $poslednii = $this->turnRepository->findPosledniiPoSessii($session->getId());

        if ($poslednii !== null && $poslednii->zhdetOtveta()) {
            throw new \DomainException('opencode.turn.awaiting_answer');
        }

        $turn = $this->launcher->zapustit($session, $command->text, []);

        return new RezultatZapuskaHoda(
            $turn->getId(),
            $turn->getStatus()->value,
            $this->runner->kanalProgressa($turn->getWorkflowId()),
        );
    }
}