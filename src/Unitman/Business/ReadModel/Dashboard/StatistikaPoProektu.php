<?php

namespace App\Unitman\Business\ReadModel\Dashboard;

final class StatistikaPoProektu
{
    public function __construct(
        public string $projectId,
        public string $projectName,
        public int $total = 0,
        public int $active = 0,
        public int $deleted = 0)
    {
    }

}
