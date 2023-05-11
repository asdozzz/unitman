<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResponseToBuildProject;
use App\Unitman\Business\Model\Unit;

interface RunnerService
{
    public function buildProject(Project $project): JobId;
    public function removeProject(Project $project): JobId;

    public function nachatSborkuUnita(Unit $unit): JobId;

    public function nachatPodgotovkuUnita(Unit $unit): JobId;

    public function nachatObnovlenieUnita(Unit $unit): JobId;

    public function nachatSbrosPodgotovkiUnita(Unit $unit): JobId;

    public function nachatZapuskUnita(Unit $unit): JobId;

    public function nachatOstanovkuUnita(Unit $unit): JobId;

    public function nachatUdalenieUnita(Unit $unit): JobId;
}
