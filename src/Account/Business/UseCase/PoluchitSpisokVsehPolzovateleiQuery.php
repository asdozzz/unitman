<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Port\UmeetPoluchatSpisokVsehPolzovatelei;
use App\Account\Business\ReadModel\UserList;

final class PoluchitSpisokVsehPolzovateleiQuery
{
    public function __construct(private UmeetPoluchatSpisokVsehPolzovatelei $umeetPoluchatSpisokVsehPolzovatelei)
    {
    }

    /**
     * @return UserList[]
     * */
    function handle(): array
    {
        return $this->umeetPoluchatSpisokVsehPolzovatelei->poluchitSpisokVsehPolzovatelei();
    }
}
