<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitReadModelById;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

final class GetUnitByIdQuery
{
    public function __construct(private CanGetUnitReadModelById $canGetUnitReadModelById)
    {

    }

    function handle(GetUnitReadModelById $command): SpisokUnitovReadModel
    {
        return $this->canGetUnitReadModelById->getById($command->id);
    }
}
