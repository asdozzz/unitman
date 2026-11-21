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
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\ReadModel\RezultatProverkiIzmeneniyWorkspace;
use App\OpenCode\Business\Utils\SlugWorkspace;
use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Command\Workspace\OstanovitWorkspace;
use App\Runner\Business\Command\Workspace\ProektWorkspaceZapros;
use App\Runner\Business\Command\Workspace\ProveritIzmeneniyaWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
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
    ) {
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

        $registered = $this->runnerApi->zapustitWorkspace(
            $workflowId,
            $this->sobratZapros($session, $workspace),
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

        $registered = $this->runnerApi->ostanovitWorkspace(
            $workflowId,
            new OstanovitWorkspace($session->getId()),
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

        $registered = $this->runnerApi->zapustitWorkspace(
            $workflowId,
            $this->sobratZapros($session, $workspace),
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

        $registered = $this->runnerApi->udalitWorkspace(
            $workflowId,
            new UdalitWorkspace($session->getId()),
        );

        $workspace->zapomnitOperaciyu(
            Workspace::OPERATION_DELETE,
            $registered !== '' ? $registered : $workflowId,
            time(),
        );
    }

    public function poluchitIzmeneniya(SessiyaOpenCode $session): RezultatProverkiIzmeneniyWorkspace
    {
        $workspace = $this->trebovatWorkspace($session);

        $projects = [];

        foreach ($workspace->getProjects() as $project) {
            $projects[] = [
                'ProjectId' => $project->projectId,
                'ProjectName' => $project->projectName,
            ];
        }

        $result = $this->runnerApi->proveritIzmeneniyaWorkspace(
            new ProveritIzmeneniyaWorkspace($session->getId(), $projects),
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
            default => null,
        };
    }

    private function sobratZapros(SessiyaOpenCode $session, Workspace $workspace): ZapustitWorkspaceZapros
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
        );
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
