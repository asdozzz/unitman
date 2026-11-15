<?php

namespace App\Unitman\Api;

use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\Command\Unit\PoluchitSostoyanieUnita;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\ReadModel\Unit\SostoyanieUnitaReadModel;
use App\Unitman\Business\UseCase\Unit\ObnovitStatistikuPoKonteineruUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\PoluchitSostoyanieUnitaQuery;
use App\Unitman\Business\UseCase\Unit\ProdvinutCiklUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\ProveritDostupKUnituUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Infra\Service\ServiceProverkiProxyHost;

final class UnitmanApi
{
    public function __construct(
        private ObnovitStatistikuPoKonteineruUnitaUseCase $obnovitStatistikuPoKonteineruUnitaUseCase,
        private ServiceProverkiProxyHost $serviceProverkiProxyHost,
        private PoluchitSostoyanieUnitaQuery $poluchitSostoyanieUnitaQuery,
        private ProveritDostupKUnituUseCase $proveritDostupKUnituUseCase,
        private SozdatUnitUseCase $sozdatUnitUseCase,
        private ProdvinutCiklUnitaUseCase $prodvinutCiklUnitaUseCase,
    )
    {
    }

    function obnovitStatistikuUnita(ObnovitStatistikuPoKontaineruUnita $command): void
    {
        $this->obnovitStatistikuPoKonteineruUnitaUseCase->handle($command);
    }

    function proveritProxyHost(?string $url): bool
    {
        return $this->serviceProverkiProxyHost->proverit($url);
    }

    /**
     * Состояние юнита для внешних модулей. Юнит может быть уже удалён —
     * тогда возвращается read-модель с `exists = false`.
     */
    function poluchitSostoyanieUnita(string $unitId): SostoyanieUnitaReadModel
    {
        return $this->poluchitSostoyanieUnitaQuery->handle(new PoluchitSostoyanieUnita($unitId));
    }

    /**
     * Доступ пользователя к юниту проверяется по доступу к проекту юнита.
     */
    function proveritDostupKUnitu(string $unitId, string $accountId): bool
    {
        return $this->proveritDostupKUnituUseCase->handle($unitId, $accountId);
    }

    /**
     * Создание юнита от имени пользователя, а не от текущего запроса.
     */
    function sozdatUnitDlyaPolzovatelya(SozdatUnit $command, string $accountId): string
    {
        return $this->sozdatUnitUseCase->handleDlyaPolzovatelya($command, $accountId);
    }

    /**
     * Продвижение конвейера юнита на один шаг, если он готов к переходу.
     */
    function prodvinutCiklUnita(string $unitId, string $accountId): void
    {
        $this->prodvinutCiklUnitaUseCase->handle($unitId, $accountId);
    }
}
