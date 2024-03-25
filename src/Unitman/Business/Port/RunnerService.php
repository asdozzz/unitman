<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResponseToBuildProject;
use App\Unitman\Business\Model\Runner\ResultatObnovleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatOstanovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSborkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSbrosaPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatZapuskaUnita;
use App\Unitman\Business\Model\Unit;

interface RunnerService
{
    public function buildProject(Project $project): Project\ProjectDataAboutBuilding;
    public function removeProject(Project $project): Project\ProjectDataAboutRemoving;

    public function nachatSborkuUnita(Unit $unit): JobId;

    public function poluchitResultatSborki(Unit $unit): ResultatSborkiUnita;

    public function nachatPodgotovkuUnita(Unit $unit): JobId;

    public function poluchitResultatPodgotovki(Unit $unit): ResultatPodgotovkiUnita;

    public function nachatObnovlenieUnita(Unit $unit): JobId;

    public function poluchitResultatObnovleniyaUnita(Unit $unit): ResultatObnovleniyaUnita;

    public function nachatSbrosPodgotovkiUnita(Unit $unit): JobId;

    public function poluchitResultatSbrosaPodgotovkiUnita(Unit $unit): ResultatSbrosaPodgotovkiUnita;

    public function nachatZapuskUnita(Unit $unit, Project $project): JobId;

    public function poluchitResultatZapuskaUnita(Unit $unit): ResultatZapuskaUnita;

    public function nachatOstanovkuUnita(Unit $unit, Project $project): JobId;

    public function poluchitResultatOstanovkiUnita(Unit $unit): ResultatOstanovkiUnita;

    public function nachatUdalenieUnita(Unit $unit): JobId;

    public function poluchitResultatUdaleniyaUnita(Unit $unit): ResultatUdaleniyaUnita;
}
