<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatUrlKlonaProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\UnitRunnerRepository;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\ReadModel\Repo\RepoCredentialGrantReadModel;

/**
 * Выдаёт раннеру короткоживущий грант на git-операцию.
 *
 * Авторизация deny-by-default:
 * - проект должен существовать и ссылаться на запрошенный repoId;
 * - если передан unitId, юнит должен принадлежать проекту, а жёсткая
 *   привязка (unit_runner) не должна указывать на другой раннер;
 * - токен расшифровывается только здесь, через WS-2 accessor.
 */
final class PoluchitKredensialyRepozitoriyaUseCase
{
    public const TTL_SECONDS = 300;

    public function __construct(
        private readonly RepoRepository $repoRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly UnitRepository $unitRepository,
        private readonly UnitRunnerRepository $unitRunnerRepository,
        private readonly UmeetPoluchatUrlKlonaProekta $urlKlonaProekta,
        private readonly UmeetShifrovatSekrety $shifr,
    ) {
    }

    /**
     * @throws \DomainException repo.credentials.denied|repo.credentials.unavailable|repo.credentials.invalid_request
     */
    public function handle(
        string $runnerId,
        string $repoId,
        string $projectId,
        ?string $unitId = null,
    ): RepoCredentialGrantReadModel {
        $repoId = trim($repoId);
        $projectId = trim($projectId);
        $unitId = $unitId !== null ? trim($unitId) : null;

        if ($repoId === '' || $projectId === '') {
            throw new \DomainException('repo.credentials.invalid_request');
        }

        try {
            $project = $this->projectRepository->getById($projectId);
            $repo = $this->repoRepository->getById($repoId);
        } catch (\Throwable) {
            // Не раскрываем существование чужих repo/project.
            throw new \DomainException('repo.credentials.denied');
        }

        if ($project->getRepoId() !== $repoId) {
            throw new \DomainException('repo.credentials.denied');
        }

        if ($unitId !== null && $unitId !== '') {
            try {
                $unit = $this->unitRepository->getById($unitId);
            } catch (\Throwable) {
                throw new \DomainException('repo.credentials.denied');
            }

            if ($unit->getProjectId() !== $projectId) {
                throw new \DomainException('repo.credentials.denied');
            }

            $boundRunnerId = $this->unitRunnerRepository->findRunnerIdByUnitId($unitId);
            if ($boundRunnerId !== null && $boundRunnerId !== '' && $boundRunnerId !== $runnerId) {
                // Юнит жёстко привязан к другому раннеру — токен ему не выдаём.
                throw new \DomainException('repo.credentials.denied');
            }
        }

        try {
            $token = $repo->rasshifrovatToken($this->shifr);
        } catch (\Throwable) {
            // Fail closed: повреждённый шифротекст/ключ не должен приводить
            // к fallback на встроенный в URL токен.
            throw new \DomainException('repo.credentials.unavailable');
        }

        if (trim($token) === '') {
            throw new \DomainException('repo.credentials.unavailable');
        }

        return new RepoCredentialGrantReadModel(
            repoId: $repoId,
            projectId: $projectId,
            repoUrl: $this->urlKlonaProekta->poluchitUrlKlonaProekta($repo, $project),
            username: $this->usernameFor($repo),
            token: $token,
            expiresAt: time() + self::TTL_SECONDS,
        );
    }

    private function usernameFor(Repo $repo): string
    {
        return match ($repo->getType()) {
            Repo\RepoType::GITHUB => 'x-access-token',
            Repo\RepoType::GITLAB => 'oauth2',
            Repo\RepoType::BITBUCKET => 'x-token-auth',
            // Gitea/Forgejo/Codeberg/Gitverse/Gitflic: PAT как пароль
            // basic-auth. Имя пользователя не является секретом.
            Repo\RepoType::GITEA,
            Repo\RepoType::FORGEJO,
            Repo\RepoType::CODEBERG,
            Repo\RepoType::GITVERSE,
            Repo\RepoType::GITFLIC => 'oauth2',
        };
    }
}
