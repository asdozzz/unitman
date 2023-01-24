<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Model\Application;

interface ApplicationRepository
{
    function save(Application $application): void;
    function getById(string $id): Application;
}
