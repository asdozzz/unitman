<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoTokenHint;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Repo\CanFindRepoDouble;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Repo\UmeetPoluchatUrlHranilisha;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class AddRepoUseCase
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private RepoRepository         $repoRepository,
        private CanGenerateGuid         $uuidGenerator,
        private CanFindRepoDouble      $canFindDouble,
        private UmeetPoluchatUrlHranilisha $umeetPoluchatUrlHranilisha,
        private UmeetShifrovatSekrety   $shifr,
    )
    {
    }

    function handle(AddRepo $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $token = trim($command->token);

        if ($token === '') {
            throw new \DomainException('repo.credentials.token_is_empty');
        }

        $repoId = $this->uuidGenerator->makeGuid();

        $url = $this->umeetPoluchatUrlHranilisha->poluchitUrlHranilisha(RepoType::from($command->repoType), $command->repoUrl);

        if ($this->canFindDouble->isExistDoubleByName($command->repoName)) {
            throw new \RuntimeException('repo.name.double');
        }

        // AAD привязывает шифротекст к идентификатору агрегата: перестановка
        // ciphertext между репозиториями не пройдёт аутентификацию GCM.
        $tokenCipher = $this->shifr->zashifrovat($token, $repoId);
        $tokenHint = RepoTokenHint::fromPlainText($token);

        $repo = Repo::addRepo($repoId, $command->repoType, $command->repoName, $url, $tokenCipher, $tokenHint);
        $this->repoRepository->save($repo);
    }
}
