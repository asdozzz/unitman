<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase\PublicAddress;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\Port\UmeetProveryatPravaAdmina;
use App\Runner\Business\Utils\RunnerPublicAddressValidator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * `PUT /api/runner/{runnerId}/public-address` — администратор задаёт
 * публичный адрес раннера.
 *
 * Валидация адреса выполняется до записи; неизвестный `runnerId` даёт
 * `runner.not_found` (HTTP 404).
 */
final class UstanovitPublicAddressUseCase
{
    public function __construct(
        private readonly RunnerRepository $runnerRepository,
        private readonly UmeetProveryatPravaAdmina $security,
        private readonly RunnerPublicAddressValidator $validator,
    ) {
    }

    public function handle(string $runnerId, string $rawPublicAddress): RunnerState
    {
        if (!$this->security->estAdministrator()) {
            throw new AccessDeniedHttpException('security.access_denied');
        }

        $publicAddress = $this->validator->validate($rawPublicAddress);

        return $this->runnerRepository->savePublicAddress($runnerId, $publicAddress);
    }
}
