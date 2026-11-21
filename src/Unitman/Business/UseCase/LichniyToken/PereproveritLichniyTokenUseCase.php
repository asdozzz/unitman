<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\LichniyToken;

use App\Unitman\Business\Command\LichniyToken\PereproveritLichniyToken;
use App\Unitman\Business\Model\LichniyToken;
use App\Unitman\Business\Model\LichniyToken\ObyomPrav;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenPublicReadModel;

/**
 * Полная перепроверка снимка прав токена по активным проектам Repo.
 */
final class PereproveritLichniyTokenUseCase
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private LichniyTokenRepository $tokenRepository,
        private LichniyTokenListRepository $tokenListRepository,
        private RepoRepository $repoRepository,
        private UmeetShifrovatSekrety $umeetShifrovatSekrety,
        private OpredelitelPravTokena $opredelitelPravTokena,
    ) {
    }

    public function handle(PereproveritLichniyToken $command): LichniyTokenPublicReadModel
    {
        $token = $this->tokenRepository->getById($command->id);

        if (!$this->securityService->isAdmin()) {
            $token->trebovatVladelca(trim($this->securityService->getCurrentUserId()));
        }

        $plain = $this->umeetShifrovatSekrety->rasshifrovat(
            $token->getTokenCipher(),
            LichniyToken::makeAad($token->getAccountId(), $token->getId()),
        );

        $repo = $this->repoRepository->getById($token->getRepoId());
        $prava = $this->opredelitelPravTokena->opredelit($repo, $plain);

        $token->obnovitPrava($prava['obyomPrav'], $prava['proektySPushom'], time());
        $this->tokenRepository->save($token);

        $readModel = $this->tokenListRepository->getById($token->getId());
        $bezPrav = $prava['obyomPrav'] === ObyomPrav::Spisok && $prava['proektySPushom'] === [];

        return LichniyTokenPublicReadModel::izList($readModel, $bezPrav);
    }
}
