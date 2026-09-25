<?php

namespace App\Unitman\Api;

use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\UseCase\Unit\ObnovitStatistikuPoKonteineruUnitaUseCase;
use App\Unitman\Infra\Service\ServiceProverkiProxyHost;

final class UnitmanApi
{
    public function __construct(
        private ObnovitStatistikuPoKonteineruUnitaUseCase $obnovitStatistikuPoKonteineruUnitaUseCase,
        private ServiceProverkiProxyHost $serviceProverkiProxyHost
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
}
