<?php

declare(strict_types=1);

namespace App\OpenCode\Acl;

use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\ProgressHodaOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Port\WorkspaceRunnerRepository;
use App\OpenCode\Business\ReadModel\RezultatProverkiIzmeneniyWorkspace;
use App\OpenCode\Business\Utils\SlugWorkspace;
use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Business\Command\Workspace\ObnovitWorkspaceZapros;
use App\Runner\Business\Command\Workspace\OstanovitWorkspace;
use App\Runner\Business\Command\Workspace\ProektWorkspaceZapros;
use App\Runner\Business\Command\Workspace\ProveritIzmeneniyaWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
use App\Runner\Business\Command\Workspace\WorkspaceFileCommand;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use App\Runner\Business\Model\GolangRunner\OpenCode\ResultatHodaOpenCode;

/**
 * Антикоррупционный слой над модулем Runner.
 *
 * Модуль OpenCode не знает про Temporal и про DTO раннера: он отдаёт
 * «намерение выполнить ход» и получает результат в собственной модели.
 */
final class RunnerAdapter implements UmeetVypolnyatHodOpenCode, UmeetUpravlyatWorkspace
{
    /** Лимит памяти workspace по умолчанию: переопределяется на стороне Go-раннера. */
    private const DEFAULT_MEMORY_LIMIT = 3072;

    public function __construct(
        private RunnerApiInterface $runnerApi,
        private UuidGenerator $uuidGenerator,
        // Необязательны для обратной совместимости ручных конструкторов в
        // тестах; DI подставляет реальные сервисы multi-runner.
        private readonly ?WorkspaceRunnerRepository $workspaceRunnerRepository = null,
        private readonly ?RunnerSelectionService $runnerSelectionService = null,
        private readonly ?UmeetUpravlyatFaylamiWorkspace $fayly = null,
    ) {
    }

    /**
     * Раннер, на котором обязан выполняться workspace.
     *
     * Workspace жёстко закреплён за одним раннером. Привязанный раннер не
     * переносится: неактивен/не найден — ошибка без fallback. Отсутствие
     * привязки (legacy/manual workspace) не даёт права на свободный выбор:
     * раннер выбирается один раз и привязка фиксируется в `workspace_runner`,
     * после чего маршрутизация идёт ровно на сохранённый раннер.
     *
     * `null` — сервисы multi-runner не подключены (legacy/ручная сборка);
     * в этом случае маршрут определяет сам Runner (DI всегда подставляет
     * реальные сервисы, поэтому для workspace-операций это недостижимо).
     *
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    private function runnerDlyaWorkspace(string $workspaceId): ?RunnerState
    {
        if ($this->runnerSelectionService === null || $this->workspaceRunnerRepository === null) {
            return null;
        }

        $runnerId = $this->workspaceRunnerRepository->findRunnerIdByWorkspaceId($workspaceId);
        if ($runnerId !== null && $runnerId !== '') {
            return $this->runnerSelectionService->getByRunnerId($runnerId);
        }

        // Нет привязки и нет живого раннера — `select()` бросит
        // `runner.not_found`; привязка при этом не создаётся.
        $runner = $this->runnerSelectionService->select();

        $this->workspaceRunnerRepository->bind($workspaceId, $runner->getId());

        // Привязка жёсткая и не перезаписывается. При конкурентном первом
        // обращении выиграть мог другой запрос — перечитываем и маршрутизируем
        // ровно на сохранённый раннер, иначе `workspace_runner` и workflow
        // разойдутся.
        $sohranenniyRunnerId = $this->workspaceRunnerRepository->findRunnerIdByWorkspaceId($workspaceId);

        if ($sohranenniyRunnerId !== null && $sohranenniyRunnerId !== '' && $sohranenniyRunnerId !== $runner->getId()) {
            return $this->runnerSelectionService->getByRunnerId($sohranenniyRunnerId);
        }

        return $runner;
    }

    /**
     * @param list<PrivyazkaKUnitu> $units
     */
    public function nachatHod(
        string $workflowId,
        string $accountId,
        string $credentialId,
        ?string $sessionId,
        array $units,
        string $prompt,
        array $answers,
        array $history,
        ?WorkspaceKontekst $workspace = null,
        bool $readOnly = false,
        ?string $token = null,
        ?string $model = null,
    ): string {
        $unitsPayload = [];
        $workspaceProjects = [];

        foreach ($units as $privyazka) {
            $unitsPayload[] = [
                'ProjectId' => $privyazka->unitProjectId,
                'UnitId' => $privyazka->unitId,
            ];
        }

        if ($workspace !== null) {
            foreach ($workspace->projects as $project) {
                $workspaceProjects[] = [
                    'ProjectId' => $project->projectId,
                    'ProjectName' => $project->projectName,
                    'ProjectCode' => $project->projectCode,
                    'CanPush' => $project->canPush,
                ];
            }
        }

        $pervyiUnit = $units[0] ?? null;

        // Ход в контексте workspace обязан идти на раннере, где поднят стенд.
        // Workspace без привязки (legacy/manual) привязывается здесь один раз;
        // свободного выбора без фиксации привязки нет. Для обычных юнит-сессий
        // (`workspace === null`) привязка не меняется: маршрут определяет
        // `unit_runner` на стороне Runner.
        $workspaceRunner = $workspace !== null
            ? $this->runnerDlyaWorkspace($workspace->workspaceId)
            : null;

        return $this->runnerApi->nachatHodOpenCode(
            $workflowId,
            new NachatHodOpenCode(
                $accountId,
                $credentialId,
                $sessionId,
                $prompt,
                $answers,
                $history,
                $pervyiUnit?->unitProjectId,
                $pervyiUnit?->unitId,
                $unitsPayload,
                $workspace?->workspaceId,
                $workspace?->readOnly ?? false,
                $workspaceProjects,
                $readOnly,
                $token,
                Model: $model,
                RunnerId: $workspaceRunner?->getId(),
                TaskQueue: $workspaceRunner?->getTaskQueue(),
            ),
        );
    }

    public function kanalProgressa(string $workflowId): string
    {
        return $this->runnerApi->poluchitKanalProgressa($workflowId);
    }

    public function poluchitResultatHoda(string $workflowId): ?RezultatHodaOpenCode
    {
        $result = $this->runnerApi->poluchitResultatHodaOpenCode($workflowId);

        return $result === null ? null : $this->prevratit($result);
    }

    public function poluchitProgressHoda(string $workflowId): ?ProgressHodaOpenCode
    {
        $progress = $this->runnerApi->poluchitProgressZadachi($workflowId);

        if ($progress === null) {
            return null;
        }

        return new ProgressHodaOpenCode(
            $progress->steps,
            $progress->finished,
            $progress->success,
            $progress->unixtime,
        );
    }

    public function zapustit(SessiyaOpenCode $session): void
    {
        $workspace = $this->trebovatWorkspace($session);
        $workflowId = $this->uuidGenerator->makeGuid();

        // Первый запуск фиксирует привязку workspace к раннеру. Именно этот
        // раннер обязан попасть в запрос: иначе workflow выберет свободный
        // раннер, а `workspace_runner` сохранит другой.
        $runner = $this->runnerDlyaWorkspace($session->getId());

        $registered = $this->runnerApi->zapustitWorkspace(
            $workflowId,
            $this->sobratZapros($session, $workspace, $runner),
        );

        $workspace->zapomnitOperaciyu(
            Workspace::OPERATION_CREATE,
            $registered !== '' ? $registered : $workflowId,
            time(),
        );
    }

    public function ostanovit(SessiyaOpenCode $session): void
    {
        $workspace = $this->trebovatWorkspace($session);
        $workflowId = $this->uuidGenerator->makeGuid();
        $runner = $this->runnerDlyaWorkspace($session->getId());

        $registered = $this->runnerApi->ostanovitWorkspace(
            $workflowId,
            new OstanovitWorkspace(
                $session->getId(),
                RunnerId: $runner?->getId(),
                TaskQueue: $runner?->getTaskQueue(),
            ),
        );

        $workspace->zapomnitOperaciyu(
            Workspace::OPERATION_STOP,
            $registered !== '' ? $registered : $workflowId,
            time(),
        );
    }

    public function vosstanovit(SessiyaOpenCode $session): void
    {
        $workspace = $this->trebovatWorkspace($session);
        $workflowId = $this->uuidGenerator->makeGuid();

        // Восстановление поднимает стенд заново на том же раннере, который
        // хранится в `workspace_runner`; запрос получает ровно его.
        $runner = $this->runnerDlyaWorkspace($session->getId());

        $registered = $this->runnerApi->zapustitWorkspace(
            $workflowId,
            $this->sobratZapros($session, $workspace, $runner),
        );

        $workspace->zapomnitOperaciyu(
            Workspace::OPERATION_START,
            $registered !== '' ? $registered : $workflowId,
            time(),
        );
    }

    public function udalit(SessiyaOpenCode $session): void
    {
        $workspace = $this->trebovatWorkspace($session);
        $workflowId = $this->uuidGenerator->makeGuid();
        $runner = $this->runnerDlyaWorkspace($session->getId());

        $registered = $this->runnerApi->udalitWorkspace(
            $workflowId,
            new UdalitWorkspace(
                $session->getId(),
                RunnerId: $runner?->getId(),
                TaskQueue: $runner?->getTaskQueue(),
            ),
        );

        $workspace->zapomnitOperaciyu(
            Workspace::OPERATION_DELETE,
            $registered !== '' ? $registered : $workflowId,
            time(),
        );
    }

    public function obnovit(SessiyaOpenCode $session, WorkspacePendingUpdate $update): string
    {
        $workspace = $this->trebovatWorkspace($session);
        $workflowId = $this->uuidGenerator->makeGuid();
        $runner = $this->runnerDlyaWorkspace($session->getId());

        $newProjects = [];
        foreach ($update->projects as $project) {
            $newProjects[] = new ProektWorkspaceZapros(
                $project->projectId,
                $project->projectName,
                $project->projectCode,
                $project->canPush,
                $project->mainBranch,
            );
        }

        $allProjects = [];
        $seen = [];

        foreach (array_merge($workspace->getProjects(), $update->projects) as $project) {
            if (isset($seen[$project->projectId])) {
                continue;
            }

            $seen[$project->projectId] = true;
            $allProjects[] = new ProektWorkspaceZapros(
                $project->projectId,
                $project->projectName,
                $project->projectCode,
                $project->canPush,
                $project->mainBranch,
            );
        }

        $fileIds = array_map(
            static fn (array $file): string => $file['fileId'],
            $update->files,
        );

        $links = $this->fayly?->poluchitSsylkiDlyaFaylov($fileIds) ?? [];
        $files = [];
        foreach ($update->files as $file) {
            $url = $links[$file['fileId']]['url'] ?? '';
            if ($url === '') {
                continue;
            }

            $files[] = new WorkspaceFileCommand($file['fileId'], $file['name'], $url);
        }

        $filesToDelete = array_map(
            static fn (array $file): string => $file['name'],
            $update->filesToDelete,
        );

        $registered = $this->runnerApi->obnovitWorkspace(
            $workflowId,
            new ObnovitWorkspaceZapros(
                $session->getId(),
                SlugWorkspace::dlya($session->getTitle(), $session->getId()),
                $workspace->getBranch(),
                $update->readOnly,
                $newProjects,
                $allProjects,
                $files,
                $filesToDelete,
                RunnerId: $runner?->getId(),
                TaskQueue: $runner?->getTaskQueue(),
            ),
        );

        return $registered !== '' ? $registered : $workflowId;
    }

    public function poluchitIzmeneniya(SessiyaOpenCode $session): RezultatProverkiIzmeneniyWorkspace
    {
        $workspace = $this->trebovatWorkspace($session);
        $runner = $this->runnerDlyaWorkspace($session->getId());

        $projects = [];

        foreach ($workspace->getProjects() as $project) {
            $projects[] = [
                'ProjectId' => $project->projectId,
                'ProjectName' => $project->projectName,
            ];
        }

        $result = $this->runnerApi->proveritIzmeneniyaWorkspace(
            new ProveritIzmeneniyaWorkspace(
                $session->getId(),
                $projects,
                RunnerId: $runner?->getId(),
                TaskQueue: $runner?->getTaskQueue(),
            ),
        );

        $mapped = [];

        foreach ($result->Projects as $project) {
            $mapped[] = [
                'projectId' => $project['ProjectId'],
                'name' => $project['Name'],
                'dirty' => $project['Dirty'],
                'files' => $project['Files'],
            ];
        }

        return new RezultatProverkiIzmeneniyWorkspace($result->Success, $mapped);
    }

    public function poluchitRezultatOperacii(SessiyaOpenCode $session): ?string
    {
        $workspace = $this->trebovatWorkspace($session);
        $workflowId = $workspace->getOperationWorkflowId();

        if ($workflowId === '') {
            return null;
        }

        try {
            return match ($workspace->getOperation()) {
                Workspace::OPERATION_CREATE, Workspace::OPERATION_START => $this->rezultat(
                    $this->runnerApi->poluchitResultatZapuskaWorkspace($workflowId),
                ),
                Workspace::OPERATION_STOP => $this->rezultat(
                    $this->runnerApi->poluchitResultatOstanovkiWorkspace($workflowId),
                ),
                Workspace::OPERATION_DELETE => $this->rezultat(
                    $this->runnerApi->poluchitResultatUdaleniyaWorkspace($workflowId),
                ),
                Workspace::OPERATION_UPDATE => $this->rezultat(
                    $this->runnerApi->poluchitRezultatObnovleniyaWorkspace($workflowId),
                ),
                default => null,
            };
        } catch (\Throwable $error) {
            // Workflow исчез из Temporal (чистка, потеря данных окружения):
            // ждать результат бессмысленно, сверка зависла бы в транзитном
            // статусе навсегда, а интерфейс крутил бы спиннер вечно. FAILURE
            // заставляет фоновую сверку пометить workspace как ERROR.
            if ($error->getMessage() === 'runner.workflow_unit_not_found') {
                return self::RESULT_FAILURE;
            }

            throw $error;
        }
    }

    private function sobratZapros(
        SessiyaOpenCode $session,
        Workspace $workspace,
        ?RunnerState $runner = null,
    ): ZapustitWorkspaceZapros
    {
        $projects = [];

        foreach ($workspace->getProjects() as $project) {
            $projects[] = new ProektWorkspaceZapros(
                $project->projectId,
                $project->projectName,
                $project->projectCode,
                $project->canPush,
                $project->mainBranch,
            );
        }

        return new ZapustitWorkspaceZapros(
            $session->getId(),
            $session->getTitle(),
            SlugWorkspace::dlya($session->getTitle(), $session->getId()),
            $workspace->getBranch(),
            $workspace->isReadOnly(),
            self::DEFAULT_MEMORY_LIMIT,
            $projects,
            RunnerId: $runner?->getId(),
            TaskQueue: $runner?->getTaskQueue(),
            Files: $this->sobratFayly($session),
        );
    }

    /**
     * @return list<WorkspaceFileCommand>
     */
    private function sobratFayly(SessiyaOpenCode $session): array
    {
        if ($this->fayly === null) {
            return [];
        }

        $models = $this->fayly->poluchitFayly($session->getId());
        $fileIds = array_map(static fn ($file): string => $file->fileId, $models);
        $links = $this->fayly->poluchitSsylkiDlyaFaylov($fileIds);

        $result = [];
        foreach ($models as $file) {
            $url = $links[$file->fileId]['url'] ?? '';
            if ($url === '') {
                continue;
            }

            $result[] = new WorkspaceFileCommand($file->fileId, $file->name, $url);
        }

        return $result;
    }

    private function trebovatWorkspace(SessiyaOpenCode $session): Workspace
    {
        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        return $workspace;
    }

    private function rezultat(?object $result): ?string
    {
        if ($result === null) {
            return null;
        }

        /** @var int|bool $success */
        $success = $result->Success ?? 0;

        return $success ? self::RESULT_SUCCESS : self::RESULT_FAILURE;
    }

    private function prevratit(ResultatHodaOpenCode $result): RezultatHodaOpenCode
    {
        return new RezultatHodaOpenCode(
            $result->Success,
            $result->Kind,
            $this->prevratitVoprosy($result->Questions),
            $result->Answer,
            $result->ErrorKey,
            $result->ExternalSessionId,
            $result->ErrorMessage,
            $result->Attachments,
        );
    }

    /**
     * Приведение вопросов раннера к форме доменной модели OpenCode.
     *
     * В контракте раннера `Description` необязателен (`Description?: string`),
     * доменная модель же гарантирует, что ключ есть всегда (`string|null`).
     * Без явного приведения отсутствующее описание превратилось бы в
     * несуществующий ключ, и JSON одного и того же ответа отличался бы по форме.
     *
     * @param list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description?: string}>
     * }> $questions
     *
     * @return list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description: string|null}>
     * }>
     */
    private function prevratitVoprosy(array $questions): array
    {
        $preobrazovannye = [];

        foreach ($questions as $question) {
            $options = [];

            foreach ($question['Options'] as $option) {
                $description = $option['Description'] ?? null;

                $options[] = [
                    'Id' => $option['Id'],
                    'Label' => $option['Label'],
                    'Description' => $description === '' ? null : $description,
                ];
            }

            $preobrazovannye[] = [
                'Id' => $question['Id'],
                'Text' => $question['Text'],
                'AllowFreeText' => $question['AllowFreeText'],
                'Options' => $options,
            ];
        }

        return $preobrazovannye;
    }
}
