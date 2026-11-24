<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase\PublicAddress;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\Port\UmeetProveryatPravaAdmina;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * `DELETE /api/runner/{runnerId}/public-address` — администратор очищает
 * публичный адрес. Раннер остаётся в реестре и может присылать health,
 * но перестаёт выбираться для сборок/ссылок.
 */
final class UdalitPublicAddressUseCase
{
    public function __construct(
        private readonly RunnerRepository $runnerRepository,
        private readonly UmeetProveryatPravaAdmina $security,
    ) {
    }

    public function handle(string $runnerId): RunnerState
    {
        if (!$this->security->estAdministrator()) {
            throw new AccessDeniedHttpException('security.access_denied');
        }

        return $this->runnerRepository->savePublicAddress($runnerId, null);
    }
}
