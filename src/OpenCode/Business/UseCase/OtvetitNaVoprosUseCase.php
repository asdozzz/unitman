<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\OtvetitNaVopros;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\RezultatZapuskaHoda;
use App\OpenCode\Business\Utils\IstoriyaDialogaOpenCode;
use App\OpenCode\Business\Utils\OtvetyNaVoprosyOpenCode;
use App\OpenCode\Business\Utils\ZapuskatelHodaOpenCode;

/**
 * Ответ пользователя на вопросы прошлого хода запускает новый ход.
 *
 * Отдельного «продолжения» не существует: раннер получает ответы вместе со
 * следующим промптом, а вопросы прошлого хода уже лежат в базе, поэтому
 * соответствие «вопрос -> ответ» проверяется здесь, до обращения к раннеру.
 */
final class OtvetitNaVoprosUseCase
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private HodOpenCodeRepository $turnRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private ZapuskatelHodaOpenCode $launcher,
        private UmeetVypolnyatHodOpenCode $runner,
    ) {
    }

    /**
     * @throws \DomainException если ход не ждёт ответа или ответ невалиден
     */
    public function handle(OtvetitNaVopros $command): RezultatZapuskaHoda
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $turn = $this->turnRepository->getById($command->turnId);
        $session = $this->sessionRepository->getById($turn->getSessionId());

        $session->trebovatVladelca($accountId);

        if (!$turn->zhdetOtveta()) {
            throw new \DomainException('opencode.turn.not_awaiting_answer');
        }

        $questions = $turn->getResult()?->questions ?? [];
        $answers = OtvetyNaVoprosyOpenCode::sobrat($questions, $command->answers);

        $prompt = IstoriyaDialogaOpenCode::otvetyVTekst($questions, $answers);

        if (trim($prompt) === '') {
            throw new \DomainException('opencode.turn.answers_required');
        }

        $novyiHod = $this->launcher->zapustit($session, $prompt, $answers);

        return new RezultatZapuskaHoda(
            $novyiHod->getId(),
            $novyiHod->getStatus()->value,
            $this->runner->kanalProgressa($novyiHod->getWorkflowId()),
        );
    }
}
