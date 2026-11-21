<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\LichniyToken;

use App\Unitman\Business\Command\LichniyToken\SpisokLichnihTokenov;
use App\Unitman\Business\Model\LichniyToken\ObyomPrav;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenPublicReadModel;

final class SpisokLichnihTokenovQuery
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private LichniyTokenListRepository $tokenListRepository,
    ) {
    }

    /**
     * @return LichniyTokenPublicReadModel[]
     */
    public function handle(SpisokLichnihTokenov $query): array
    {
        $tokens = $this->securityService->isAdmin()
            ? $this->tokenListRepository->findAll()
            : $this->tokenListRepository->findByAccountId(trim($this->securityService->getCurrentUserId()));

        $result = [];

        foreach ($tokens as $token) {
            if ($token->deleted) {
                continue;
            }

            $bezPrav = $token->obyomPrav === ObyomPrav::Spisok->value && $token->proektySPushom === [];
            $result[] = LichniyTokenPublicReadModel::izList($token, $bezPrav);
        }

        return $result;
    }
}
