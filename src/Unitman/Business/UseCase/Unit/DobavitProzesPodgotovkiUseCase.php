<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;

final class DobavitProzesPodgotovkiUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService,
        private CanGenerateGuid         $uuidGenerator,
        private ProverkaSvobodyUnita    $proverkaSvobody,
    )
    {
    }

    function handle(string $unitId): void
    {
        $this->handleDlyaPolzovatelya($unitId, $this->securityService->getCurrentUserId());
    }

    /**
     * Тот же сценарий, но от имени явно переданного пользователя: нужно
     * интеграциям (сессии OpenCode), которые действуют не от текущего запроса.
     *
     * @param bool $proveritSvobodu Проверять ли внешнюю занятость юнита. По умолчанию
     *                              проверяем: этим методом ходят пользовательские
     *                              сценарии. `ProdvinutCiklUnitaUseCase` передаёт
     *                              `false`, потому что его вызывает сам OpenCode —
     *                              тот, кто этот захват и держит.
     */
    function handleDlyaPolzovatelya(string $unitId, string $userId, bool $proveritSvobodu = true): void
    {
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = $this->unitRepository->getById($unitId);

        if ($proveritSvobodu) {
            $this->proverkaSvobody->proverit($unit);
        }

        $unit->dobavitProzesPodgotovki($userId, $prozesId);
        $this->unitRepository->save($unit);
    }
}
