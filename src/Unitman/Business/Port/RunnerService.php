<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Runner\ResponseToBuildProject;
use App\Unitman\Business\Model\Unit;

interface RunnerService
{
    public function buildProject(Project $project): string;
    public function removeProject(Project $project): string;

    public function nachatSborkuUnita(Unit $unit): string;

    public function nachatPodgotovkuUnita(Unit $unit): string;

    public function nachatObnovlenieUnita(Unit $unit): string;

    public function nachatSbrosPodgotovkiUnita(Unit $unit): string;

    public function nachatZapuskUnita(Unit $unit): string;

    public function nachatOstanovkuUnita(Unit $unit): string;

    public function nachatUdalenieUnita(Unit $unit): string;
}
