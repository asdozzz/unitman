<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\AgentMode\Utils\OpredelitelRezhimaAgentaHoda;
use App\OpenCode\Business\Command\OtvetitNaVopros;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\RezultatZapuskaHoda;
use App\OpenCode\Business\Utils\IstoriyaDialogaOpenCode;
use App\OpenCode\Business\Utils\OpredelitelRekvizitovHodaOpenCode;
use App\OpenCode\Business\Utils\OtvetyNaVoprosyOpenCode;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Utils\PropuskHodaOpenCode;
use App\OpenCode\Business\Utils\ZapuskatelHodaOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\UseCase\OtvetitNaVoprosWorkspaceUseCase;

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
        private PropuskHodaOpenCode $propusk,
        private ProverkaDostupaKSessii $dostup,
        private OpredelitelRekvizitovHodaOpenCode $rekvizity,
        private OpredelitelRezhimaAgentaHoda $rezhimyAgentov,
        private ?WorkspaceTurnRepository $workspaceTurns = null,
        private ?WorkspaceFeatureFlags $flags = null,
        private ?OtvetitNaVoprosWorkspaceUseCase $workspaceUseCase = null,
    ) {
    }

    /**
     * @throws \DomainException если ход не ждёт ответа или ответ невалиден
     */
    public function handle(OtvetitNaVopros $command): RezultatZapuskaHoda
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        // Новый агрегат содержит только workspace-ходы: найденный там ход
        // однозначно означает workspace, unit-ветка не затрагивается.
        if ($this->flags?->pisatVNoviyAgregat() === true
            && $this->workspaceUseCase !== null
            && $this->workspaceTurns?->findById($command->turnId) !== null
        ) {
            return $this->workspaceUseCase->handle(
                $command->turnId,
                array_map(
                    static fn ($answer): array => is_array($answer) ? $answer : $answer->toArray(),
                    $command->answers,
                ),
                $command->model,
                $command->credentialId,
                $command->agentModeId,
            );
        }

        $turn = $this->turnRepository->getById($command->turnId);
        $session = $this->sessionRepository->getById($turn->getSessionId());

        $uchastnik = $this->dostup->trebovat($session, $accountId, RolUchastnikaSessii::WRITER);
        $units = $this->propusk->proverit($session);

        if (!$turn->zhdetOtveta()) {
            throw new \DomainException('opencode.turn.not_awaiting_answer');
        }

        $questions = $turn->getResult()?->questions ?? [];
        $answers = OtvetyNaVoprosyOpenCode::sobrat($questions, $command->answers);

        $prompt = IstoriyaDialogaOpenCode::otvetyVTekst($questions, $answers);

        if (trim($prompt) === '') {
            throw new \DomainException('opencode.turn.answers_required');
        }

        $rezhimAgenta = $this->rezhimyAgentov->opredelit(
            $accountId,
            OpredelitelRezhimaAgentaHoda::CHAT_UNIT,
            $command->agentModeId,
        );

        $istochnik = $this->rekvizity->opredelit(
            $session,
            $accountId,
            $uchastnik,
            $command->credentialId ?? $rezhimAgenta->credentialId,
        );

        $novyiHod = $this->launcher->zapustit(
            $session,
            $prompt,
            $answers,
            $units,
            $command->model,
            $istochnik,
            $rezhimAgenta,
        );

        return new RezultatZapuskaHoda(
            $novyiHod->getId(),
            $novyiHod->getStatus()->value,
            $this->runner->kanalProgressa($novyiHod->getWorkflowId()),
        );
    }
}
