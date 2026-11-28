<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Utils;

use App\OpenCode\Business\AgentMode\ReadModel\RezhimAgentaHoda;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\IstochnikRekvizitovOpenCode;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Utils\IstoriyaDialogaOpenCode;
use App\OpenCode\Business\Utils\OpredelitelModeliHodaOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;

/**
 * Старт хода на самостоятельном workspace-агрегате.
 *
 * Семантика повторяет легаси `ZapuskatelHodaOpenCode`, но без юнитов:
 * - проверка доступа автора ко всем проектам workspace на каждом ходе;
 * - резервация workspace в общей таблице `opencode_session_running_turn`;
 * - старт workflow до сохранения хода (иначе отказ Temporal оставит RUNNING);
 * - автор и реквизиты записываются в ход.
 */
final class ZapuskatelHodaWorkspace
{
    public function __construct(
        private readonly WorkspaceTurnRepository $turns,
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly UmeetVypolnyatHodOpenCode $runner,
        private readonly UuidGenerator $uuidGenerator,
        private readonly OpredelitelModeliHodaOpenCode $modeli,
        private readonly UmeetUpravlyatProektamiWorkspace $projectami,
    ) {
    }

    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     */
    public function zapustit(
        WorkspaceSession $workspace,
        string $prompt,
        array $answers,
        ?string $model,
        IstochnikRekvizitovOpenCode $istochnik,
        ?RezhimAgentaHoda $rezhimAgenta = null,
    ): HodOpenCode {
        $now = time();
        $accountId = $istochnik->accountId;
        $credentialId = $istochnik->credentialId;
        $modelPoUmolchaniyu = $model
            ?? $rezhimAgenta?->model
            ?? $istochnik->modelPoUmolchaniyu;

        // Права в Unitman могли отозвать после приглашения: проверяем на каждом
        // ходе, а не только при добавлении участника.
        $this->trebovatDostupKProektam($workspace, $accountId);

        $razreshennayaModel = $this->modeli->opredelit(
            $accountId,
            $credentialId,
            $model,
            $modelPoUmolchaniyu,
        );

        $turn = HodOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $workspace->getId(),
            $this->turns->sleduyushiiSeq($workspace->getId()),
            $this->uuidGenerator->makeGuid(),
            $prompt,
            $answers,
            $now,
            $razreshennayaModel,
            authorAccountId: $accountId,
            credentialId: $credentialId,
            agentModeId: $rezhimAgenta?->modeId,
            agentModeName: $rezhimAgenta?->modeName,
        );

        $history = IstoriyaDialogaOpenCode::sobrat(
            $this->turns->findBySessionId($workspace->getId()),
        );

        $state = $workspace->getWorkspace();
        $readOnly = $state->isReadOnly();
        $kontekst = new WorkspaceKontekst(
            $workspace->getId(),
            $readOnly,
            $state->getProjects(),
        );

        $zanyata = false;

        try {
            // Резервация — единственный атомарный барьер против параллельных
            // ходов: проверка «последний ход не выполняется» от гонки не спасает.
            $this->turns->zanyatSessiyu(
                $workspace->getId(),
                $turn->getId(),
                $now,
                WorkspaceTurnRepository::TTL_ZANYATOSTI_SEKUND,
            );
            $zanyata = true;

            $this->runner->nachatHod(
                $turn->getWorkflowId(),
                $accountId,
                $credentialId,
                $workspace->getExternalSessionId(),
                [],
                $prompt,
                $answers,
                $history,
                $kontekst,
                $readOnly,
                null,
                model: $razreshennayaModel,
                rezhimAgenta: $rezhimAgenta,
            );
        } catch (\Throwable $exception) {
            if ($zanyata) {
                $this->otpusitSessiyu($workspace->getId(), $turn->getId());
            }

            throw $exception;
        }

        $this->turns->save($turn);
        $workspace->otmetitIzmenenie($now);
        $this->workspaces->update($workspace);

        return $turn;
    }

    /**
     * @throws \DomainException `opencode.workspace.project_access_revoked`
     */
    private function trebovatDostupKProektam(WorkspaceSession $workspace, string $accountId): void
    {
        $projectIds = array_map(
            static fn ($project): string => $project->projectId,
            $workspace->getWorkspace()->getProjects(),
        );

        if (!$this->projectami->estDostupKProektam($projectIds, $accountId)) {
            throw new \DomainException('opencode.workspace.project_access_revoked');
        }
    }

    private function otpusitSessiyu(string $workspaceId, string $turnId): void
    {
        try {
            $this->turns->osvoboditSessiyu($workspaceId, $turnId);
        } catch (\Throwable) {
            // Основная ошибка важнее: TTL перехватит аренду при следующем старте.
        }
    }
}
