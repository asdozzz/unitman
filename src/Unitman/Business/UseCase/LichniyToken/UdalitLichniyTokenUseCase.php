<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\LichniyToken;

use App\Unitman\Business\Command\LichniyToken\UdalitLichniyToken;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class UdalitLichniyTokenUseCase
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private LichniyTokenRepository $tokenRepository,
    ) {
    }

    public function handle(UdalitLichniyToken $command): void
    {
        $token = $this->tokenRepository->getById($command->id);

        if (!$this->securityService->isAdmin()) {
            $token->trebovatVladelca(trim($this->securityService->getCurrentUserId()));
        }

        $token->udalit();
        $this->tokenRepository->save($token);
    }
}
