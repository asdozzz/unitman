<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\LichniyToken;

use App\Unitman\Business\Command\LichniyToken\DobavitLichniyToken;
use App\Unitman\Business\Model\LichniyToken;
use App\Unitman\Business\Model\LichniyToken\ObyomPrav;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Repo\UmeetProveryatTokenHranilisha;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenPublicReadModel;

/**
 * Добавление личного токена текущего пользователя.
 *
 * Открытый токен существует только внутри метода: в агрегат уходит шифротекст,
 * наружу — hint и объём прав.
 */
final class DobavitLichniyTokenUseCase
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private RepoRepository $repoRepository,
        private CanGenerateGuid $guidGenerator,
        private LichniyTokenRepository $tokenRepository,
        private LichniyTokenListRepository $tokenListRepository,
        private UmeetShifrovatSekrety $umeetShifrovatSekrety,
        private OpredelitelPravTokena $opredelitelPravTokena,
        private UmeetProveryatTokenHranilisha $proverkaTokena,
    ) {
    }

    public function handle(DobavitLichniyToken $command): LichniyTokenPublicReadModel
    {
        $accountId = trim($this->securityService->getCurrentUserId());

        if ($accountId === '') {
            throw new \DomainException('security.access_denied');
        }

        $token = trim($command->token);

        if ($token === '') {
            throw new \DomainException('lichniy_token.pustoy_token');
        }

        $repo = $this->repoRepository->getById($command->repoId);

        $this->proveritDublikat($accountId, $repo->getId(), $token);

        if (!$this->proverkaTokena->proveritToken($token, $repo)) {
            throw new \DomainException('lichniy_token.ne_validen');
        }

        $prava = $this->opredelitelPravTokena->opredelit($repo, $token);

        $tokenId = $this->guidGenerator->makeGuid();

        $aggregate = LichniyToken::dobavit(
            $tokenId,
            $accountId,
            $repo->getId(),
            $command->nazvanie,
            $this->umeetShifrovatSekrety->zashifrovat(
                $token,
                LichniyToken::makeAad($accountId, $tokenId),
            ),
            LichniyToken::makeHint($token),
            $prava['obyomPrav'],
            $prava['proektySPushom'],
            time(),
        );

        $this->tokenRepository->save($aggregate);

        $bezPrav = $prava['obyomPrav'] === ObyomPrav::Spisok && $prava['proektySPushom'] === [];

        return LichniyTokenPublicReadModel::izList(
            $this->tokenListRepository->getById($tokenId),
            $bezPrav,
        );
    }

    private function proveritDublikat(string $accountId, string $repoId, string $token): void
    {
        foreach ($this->tokenListRepository->findByAccountIdAndRepoId($accountId, $repoId) as $existing) {
            if ($existing->deleted) {
                continue;
            }

            try {
                $plain = $this->umeetShifrovatSekrety->rasshifrovat(
                    $existing->tokenCipher,
                    LichniyToken::makeAad($accountId, $existing->id),
                );
            } catch (\DomainException) {
                continue;
            }

            if (hash_equals($plain, $token)) {
                throw new \DomainException('lichniy_token.dublikat');
            }
        }
    }
}
