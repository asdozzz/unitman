<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\DobavitUchastnika;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\DobavitUchastnikaVWorkspaceSessiiUseCase;

/**
 * Приглашение участника в workspace.
 *
 * Приглашать может только владелец. Кандидат обязан иметь доступ ко всем
 * проектам workspace: иначе read-only IDE и общий чат раскрыли бы ему код,
 * к которому у него нет прав.
 */
final class DobavitUchastnikaUseCase
{
    public const MAX_UCHASTNIKOV = 20;

    public function __construct(
        private readonly SessiyaOpenCodeRepository $sessionRepository,
        private readonly UchastnikiSessiiRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatProektamiWorkspace $projectami,
        private readonly ProverkaDostupaKSessii $dostup,
        private readonly ?WorkspaceFeatureFlags $flags = null,
        private readonly ?DobavitUchastnikaVWorkspaceSessiiUseCase $workspaceUseCase = null,
    ) {
    }

    public function handle(DobavitUchastnika $command): void
    {
        if ($this->flags?->pisatVNoviyAgregat() === true && $this->workspaceUseCase !== null) {
            $this->workspaceUseCase->handle($command);

            return;
        }

        $accountId = $this->credentials->tekuschiiAccountId();
        $session = $this->sessionRepository->getById(trim($command->workspaceId));

        $this->dostup->trebovat($session, $accountId, RolUchastnikaSessii::OWNER);
        $workspace = $this->trebovatWorkspace($session);

        $uchastnikId = trim($command->accountId);

        if ($uchastnikId === '') {
            throw new \DomainException('opencode.participant.account_required');
        }

        if ($session->prinadlezhit($uchastnikId)) {
            throw new \DomainException('opencode.participant.already_owner');
        }

        $rol = RolUchastnikaSessii::izStroki($command->role);

        if ($rol === RolUchastnikaSessii::OWNER) {
            throw new \DomainException('opencode.participant.owner_role_immutable');
        }

        if ($this->uchastniki->find($session->getId(), $uchastnikId) !== null) {
            throw new \DomainException('opencode.participant.already_exists');
        }

        if (count($this->uchastniki->findBySessionId($session->getId())) >= self::MAX_UCHASTNIKOV) {
            throw new \DomainException('opencode.participant.limit_exceeded');
        }

        if (!$this->projectami->estDostupKProektam($this->proektIds($workspace), $uchastnikId)) {
            throw new \DomainException('opencode.participant.project_access_denied');
        }

        $credentialId = $this->normalize($command->credentialId);

        if ($credentialId !== null) {
            // Реквизиты должны принадлежать приглашаемому, а не владельцу.
            $this->credentials->trebovatVladelca($uchastnikId, $credentialId);
        }

        $now = time();

        $this->uchastniki->save(UchastnikSessii::create(
            $session->getId(),
            $uchastnikId,
            $rol,
            $credentialId,
            $command->model,
            $accountId,
            $now,
        ));
    }

    /**
     * @return list<string>
     */
    private function proektIds(Workspace $workspace): array
    {
        $ids = [];

        foreach ($workspace->getProjects() as $project) {
            $ids[] = $project->projectId;
        }

        return $ids;
    }

    private function trebovatWorkspace(SessiyaOpenCode $session): Workspace
    {
        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        return $workspace;
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
