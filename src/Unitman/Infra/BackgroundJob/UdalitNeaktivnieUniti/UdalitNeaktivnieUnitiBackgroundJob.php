<?php

namespace App\Unitman\Infra\BackgroundJob\UdalitNeaktivnieUniti;

use App\BackgroundJob\Infra\Service\BackgroundJobInterface;
use App\Unitman\Business\UseCase\Unit\UdalitNeaktivnieUnitiUseCase;

final class UdalitNeaktivnieUnitiBackgroundJob  implements BackgroundJobInterface
{
    public function __construct(private UdalitNeaktivnieUnitiUseCase $useCase)
    {
    }

    function getName(): string
    {
        return 'udalenie_neaktivnih_unitov';
    }

    function run(): bool
    {
        $this->useCase->handle(60*60*24*14);
        return true;
    }

    function getDelay(): int
    {
        return 60*60*24;
    }

    function getProcessNum(): int
    {
        return 1;
    }
}
