<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;

final class PoluchitSpisokProektovPolzovatelyaUseCase
{
    public function __construct(
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $umeetPoluchatSpisokMoihProektov,
    )
    {
    }

    function handle(string $userId): array
    {
       return $this->umeetPoluchatSpisokMoihProektov->poluchitSpisokProektovDlyPolzovatelya($userId);
    }
}
