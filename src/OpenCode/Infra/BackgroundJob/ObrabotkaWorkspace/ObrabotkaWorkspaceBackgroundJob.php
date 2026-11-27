<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\BackgroundJob\ObrabotkaWorkspace;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Utils\ZapuskatelHodaOpenCode;
use Psr\Log\LoggerInterface;

/**
 * Сверка операций workspace.
 *
 * Каждый переход выполняется атомарно (`perevestiStatusWorkspace` с ожидаемыми
 * статусами), поэтому повторный запуск не создаёт вторую остановку/удаление.
 * Ошибка одного workspace логируется и не прерывает обработку пачки.
 *
 * В выборку входят и архивные сессии с транзитным статусом workspace: удаление
 * архива (`ARCHIVED → DELETING`) и восстановление из архива
 * (`ARCHIVED → STARTING`) нельзя терять, иначе они навсегда залипают.
 */
final class ObrabotkaWorkspaceBackgroundJob extends AbstractBackgroundJob
{
    private const BATCH_LIMIT = 50;

    private const ERROR_KEY = 'opencode.workspace.operation_failed';

    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetUpravlyatWorkspace $workspaceManager,
        private ZapuskatelHodaOpenCode $launcher,
        private LoggerInterface $logger,
        private ?UmeetUpravlyatFaylamiWorkspace $fayly = null,
    ) {
    }

    public function getName(): string
    {
        return 'opencode_workspace_reconcile';
    }

    public function getGroup(): BackgroundJobGroup
    {
        return BackgroundJobGroup::timers;
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
            $sessions = $this->sessionRepository->findAktivnieWorkspace(self::BATCH_LIMIT);
        } catch (\Throwable $error) {
            $this->logger->error('opencode.workspace_reconcile.batch_load_failed', [
                'exception' => $error,
            ]);

            return true;
        }

        foreach ($sessions as $session) {
            $this->obrabotat($session);
        }

        return true;
    }

    private function obrabotat(SessiyaOpenCode $session): void
    {
        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            return;
        }

        try {
            match ($workspace->getStatus()) {
                StatusWorkspace::CREATING => $this->obrabotatSozdanie($session),
                StatusWorkspace::STARTING => $this->obrabotatZapusk($session),
                StatusWorkspace::ARCHIVING => $this->obrabotatArhivaciyu($session),
                StatusWorkspace::DELETING => $this->obrabotatUdalenie($session),
                StatusWorkspace::RUNNING => $this->obrabotatObnovlenieIliPrompt($session),
                default => null,
            };
        } catch (\Throwable $error) {
            $this->logger->error('opencode.workspace_reconcile.workspace_failed', [
                'workspaceId' => $session->getId(),
                'status' => $workspace->getStatus()->value,
                'exception' => $error,
            ]);
        }
    }

    private function obrabotatSozdanie(SessiyaOpenCode $session): void
    {
        $result = $this->workspaceManager->poluchitRezultatOperacii($session);

        if ($result === null) {
            return;
        }

        if ($result === UmeetUpravlyatWorkspace::RESULT_FAILURE) {
            $this->otmetitOshibku($session, StatusWorkspace::CREATING);

            return;
        }

        if (!$this->sessionRepository->perevestiStatusWorkspace(
            $session->getId(),
            [StatusWorkspace::CREATING],
            StatusWorkspace::RUNNING,
        )) {
            return;
        }

        $fresh = $this->sessionRepository->getById($session->getId());
        $this->zapustitPervyiPrompt($fresh);
    }

    private function obrabotatZapusk(SessiyaOpenCode $session): void
    {
        $result = $this->workspaceManager->poluchitRezultatOperacii($session);

        if ($result === null) {
            return;
        }

        if ($result === UmeetUpravlyatWorkspace::RESULT_FAILURE) {
            $this->otmetitOshibku($session, StatusWorkspace::STARTING);

            return;
        }

        if (!$this->sessionRepository->perevestiStatusWorkspace(
            $session->getId(),
            [StatusWorkspace::STARTING],
            StatusWorkspace::RUNNING,
        )) {
            return;
        }

        $fresh = $this->sessionRepository->getById($session->getId());
        $fresh->snovaAktivna(time());
        $this->sessionRepository->update($fresh);
    }

    private function obrabotatArhivaciyu(SessiyaOpenCode $session): void
    {
        $result = $this->workspaceManager->poluchitRezultatOperacii($session);

        if ($result === null) {
            return;
        }

        if ($result === UmeetUpravlyatWorkspace::RESULT_FAILURE) {
            $this->otmetitOshibku($session, StatusWorkspace::ARCHIVING);

            return;
        }

        if (!$this->sessionRepository->perevestiStatusWorkspace(
            $session->getId(),
            [StatusWorkspace::ARCHIVING],
            StatusWorkspace::ARCHIVED,
        )) {
            return;
        }

        $fresh = $this->sessionRepository->getById($session->getId());
        $fresh->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, time());
        $this->sessionRepository->update($fresh);
    }

    private function obrabotatUdalenie(SessiyaOpenCode $session): void
    {
        $result = $this->workspaceManager->poluchitRezultatOperacii($session);

        if ($result === null) {
            return;
        }

        if ($result === UmeetUpravlyatWorkspace::RESULT_FAILURE) {
            $this->otmetitOshibku($session, StatusWorkspace::DELETING);

            return;
        }

        // Артефакты workspace удаляем до строк БД: сбой Mediateka не должен
        // блокировать финальное удаление сессии (best-effort).
        try {
            $this->fayly?->udalitFajlyWorkspace($session->getId());
        } catch (\Throwable $error) {
            $this->logger->error('opencode.workspace.files_delete_failed', [
                'workspaceId' => $session->getId(),
                'exception' => $error,
            ]);
        }

        $this->sessionRepository->deleteWorkspace($session->getId());
    }

    private function obrabotatObnovlenieIliPrompt(SessiyaOpenCode $session): void
    {
        $pending = $this->sessionRepository->findWorkspaceUpdate($session->getId());

        if ($pending === null) {
            $this->zapustitPervyiPrompt($session);

            return;
        }

        $result = $this->workspaceManager->poluchitRezultatOperacii($session);

        if ($result === null) {
            return;
        }

        $workspace = $session->getWorkspace();
        if ($workspace === null) {
            return;
        }

        if ($result === UmeetUpravlyatWorkspace::RESULT_FAILURE) {
            $this->otkatitObnovlenie($pending['update']->files);
            $this->sessionRepository->deleteWorkspaceUpdate($session->getId());
            $workspace->sbrositOperaciyu(time());
            $workspace->otmetitOshibku('opencode.workspace.update_failed', time());
            $this->sessionRepository->update($session);

            return;
        }

        $now = time();

        if ($pending['update']->projects !== []) {
            $workspace->dobavitProekty($pending['update']->projects, $now);
        }

        if ($pending['update']->files !== []) {
            $this->fayly->otmetitPrimenenimi(array_map(
                static fn (array $file): string => $file['fileId'],
                $pending['update']->files,
            ));
        }

        foreach ($pending['update']->filesToDelete as $file) {
            $this->fayly->udalitFayl($file['fileId']);
        }

        $workspace->sbrositOperaciyu($now);
        $workspace->ochistitOshibku($now);
        $this->sessionRepository->update($session);
        $this->sessionRepository->deleteWorkspaceUpdate($session->getId());

        $this->zapustitPervyiPrompt($session);
    }

    /**
     * Откатывает только что прикреплённые файлы, если обновление не удалось.
     *
     * @param list<array{fileId: string, name: string}> $files
     */
    private function otkatitObnovlenie(array $files): void
    {
        foreach ($files as $file) {
            try {
                $this->fayly->udalitFayl($file['fileId']);
            } catch (\Throwable $error) {
                $this->logger->error('opencode.workspace_reconcile.rollback_file_failed', [
                    'fileId' => $file['fileId'],
                    'exception' => $error,
                ]);
            }
        }
    }

    private function zapustitPervyiPrompt(SessiyaOpenCode $session): void
    {
        $workspace = $session->getWorkspace();

        if ($workspace === null || $workspace->getInitialPromptStartedAt() !== null) {
            return;
        }

        try {
            $this->launcher->zapustit($session, $workspace->getInitialPrompt(), [], []);
        } catch (\Throwable $error) {
            // Workspace остаётся RUNNING: пользователь может отправить промпт
            // вручную, а ошибка хода будет видна в уведомлении.
            $this->logger->error('opencode.workspace_reconcile.initial_prompt_failed', [
                'workspaceId' => $session->getId(),
                'exception' => $error,
            ]);

            return;
        }

        $workspace->otmetitStartPervogoPrompta(time());
        $this->sessionRepository->update($session);
    }

    private function otmetitOshibku(SessiyaOpenCode $session, StatusWorkspace $from): void
    {
        if (!$this->sessionRepository->perevestiStatusWorkspace(
            $session->getId(),
            [$from],
            StatusWorkspace::ERROR,
        )) {
            return;
        }

        $fresh = $this->sessionRepository->getById($session->getId());
        $fresh->getWorkspace()?->otmetitOshibku(self::ERROR_KEY, time());
        $this->sessionRepository->update($fresh);
    }
}
