<?php

namespace App\Unitman\Api;

use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\UseCase\Unit\ObnovitStatistikuPoKonteineruUnitaUseCase;

final class UnitmanApi
{
    public function __construct(
        private ObnovitStatistikuPoKonteineruUnitaUseCase $obnovitStatistikuPoKonteineruUnitaUseCase,
    )
    {
    }

    function obnovitStatistikuUnita(ObnovitStatistikuPoKontaineruUnita $command): void
    {
        $this->obnovitStatistikuPoKonteineruUnitaUseCase->handle($command);
    }
}
