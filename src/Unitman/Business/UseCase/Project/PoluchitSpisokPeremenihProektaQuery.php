<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PoluchitSpisokPeremenihProekta;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokPeremenihProekta;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListVariable;

final class PoluchitSpisokPeremenihProektaQuery
{
    public function __construct(private UmeetPoluchatSpisokPeremenihProekta $umeetPoluchatSpisokPolzovateleiProekta)
    {
    }

    /**
     * @return ProjectListVariable[]
     * */
    function handle(PoluchitSpisokPeremenihProekta $command): array
    {
        return $this->umeetPoluchatSpisokPolzovateleiProekta->poluchitSpisokPeremenihProekta($command->id);
    }
}
