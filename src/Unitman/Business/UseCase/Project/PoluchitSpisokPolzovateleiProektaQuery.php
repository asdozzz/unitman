<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PoluchitSpisokPolzovateleiProekta;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokPolzovateleiProekta;
use App\Unitman\Business\ReadModel\ProjectUsersList;

final class PoluchitSpisokPolzovateleiProektaQuery
{
    public function __construct(private UmeetPoluchatSpisokPolzovateleiProekta $umeetPoluchatSpisokPolzovateleiProekta)
    {
    }

    /**
     * @return ProjectUsersList[]
     * */
    function handle(PoluchitSpisokPolzovateleiProekta $command): array
    {
        return $this->umeetPoluchatSpisokPolzovateleiProekta->poluchitSpisokPolzovateleiProekta($command->id);
    }
}
