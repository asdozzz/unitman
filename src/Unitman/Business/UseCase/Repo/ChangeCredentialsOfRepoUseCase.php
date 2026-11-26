<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\ChangeCredentialsOfRepo;
use App\Unitman\Business\Model\Repo\RepoTokenHint;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Repo\UmeetPoluchatUrlHranilisha;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\Port\UnitmanSecurityService;

/**
 * Изменение учётных данных репозитория.
 *
 * Контракт write-only (WS-5): если `token` не передан/пуст, текущий секрет
 * сохраняется. Событие изменения учётных данных при этом не порождается,
 * если не изменился и URL. Непустой `token` шифруется с AAD = repoId.
 */
final class ChangeCredentialsOfRepoUseCase
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private RepoRepository         $repoRepository,
        private UmeetPoluchatUrlHranilisha $umeetPoluchatUrlHranilisha,
        private UmeetShifrovatSekrety   $shifr,
    )
    {
    }

    function handle(ChangeCredentialsOfRepo $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $repo = $this->repoRepository->getById($command->repoId);
        $currentUrl = $repo->getCredentials()->url;

        $url = $this->umeetPoluchatUrlHranilisha->poluchitUrlHranilisha(
            $repo->getType(),
            $command->repoUrl ?? $currentUrl,
        );

        $token = $command->token !== null ? trim($command->token) : '';

        if ($token === '') {
            // Секрет не передан — сохраняем существующий шифротекст.
            if ($url === $currentUrl) {
                return;
            }

            $repo->changeCredentials($url, $repo->getTokenCipher(), $repo->getTokenHint());
            $this->repoRepository->save($repo);

            return;
        }

        $currentToken = $repo->rasshifrovatToken($this->shifr);

        if ($url === $currentUrl && hash_equals($currentToken, $token)) {
            throw new \DomainException('repo.new_credentials_equals_old');
        }

        $tokenCipher = $this->shifr->zashifrovat($token, $repo->getId());
        $tokenHint = RepoTokenHint::fromPlainText($token);

        $repo->changeCredentials($url, $tokenCipher, $tokenHint);
        $this->repoRepository->save($repo);
    }
}
