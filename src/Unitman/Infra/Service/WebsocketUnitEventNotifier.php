<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Service;

use App\Unitman\Business\Port\Unit\UmeetOpoveshatObIzmeneniiUnita;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\WebsocketUnitEventReadModel;
use App\Unitman\Infra\Jobs\WebsocketUnitEventReadModelJobHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use Psr\Log\LoggerInterface;

final class WebsocketUnitEventNotifier implements UmeetOpoveshatObIzmeneniiUnita
{
    public function __construct(
        private ZadachaDlyOcherediService $zadachaDlyOcherediService,
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService,
        private LoggerInterface $logger,
    ) {
    }

    public function opoveshatObObnovlenii(string $unitId): void
    {
        try {
            $unit = $this->unitRepository->getById($unitId);
            $user = $this->securityService->getUserById($unit->getAuthorId());

            $model = new WebsocketUnitEventReadModel(
                $unitId,
                WebsocketUnitEventReadModel::OBNOVLEN,
                $user->id,
                $user->email,
                $unit->getName(),
            );

            $this->zadachaDlyOcherediService->dobavitZadachuVOchered(
                WebsocketUnitEventReadModelJobHandler::QUEUE_NAME,
                $model,
                2,
                2,
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Не удалось отправить websocket-уведомление об обновлении контейнера юнита',
                [
                    'unitId' => $unitId,
                    'exception' => $e,
                ],
            );
        }
    }
}
