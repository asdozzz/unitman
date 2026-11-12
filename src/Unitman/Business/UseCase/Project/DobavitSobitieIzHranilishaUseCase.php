<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\DobavitSobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Queue\ZadachaVOchered;
use App\Unitman\Business\Utils\QueueNameEnum;

final class DobavitSobitieIzHranilishaUseCase
{
    public function __construct(private ZadachaVOchered $zadachaVOchered, private CanGenerateGuid $canGenerateGuid)
    {
    }

    function handle(DobavitSobitieIzHranilisha $command): string
    {
        $id = $this->canGenerateGuid->makeGuid();
        $model = new SobitieIzHranilisha($id, $command->projectId, $command->payload);
        $this->zadachaVOchered->dobavitZadachuVOchered(QueueNameEnum::SOBITIE_IZ_HRANILISHA->value, $model, 2);

        return $model->id;
    }
}
