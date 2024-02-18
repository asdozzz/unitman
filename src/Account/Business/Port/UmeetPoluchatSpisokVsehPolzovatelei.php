<?php

namespace App\Account\Business\Port;

use App\Account\Business\ReadModel\AccountForManaging;
use App\Account\Business\ReadModel\UserList;

interface UmeetPoluchatSpisokVsehPolzovatelei
{
    /**
     * @return UserList[]
     * */
    function poluchitSpisokVsehPolzovatelei(): array;
    /**
     * @return AccountForManaging[]
     * */
    function poluchitSpisokVsehPolzovateleiDlyAdministrirovaniya(): array;
}
