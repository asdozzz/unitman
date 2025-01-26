<?php

namespace App\Unitman\Business\Port;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResponseToBuildProject;
use App\Unitman\Business\Model\Runner\ResultatDeistviyaiUnita;
use App\Unitman\Business\Model\Runner\ResultatIzmeneniyaVetkiUnita;
use App\Unitman\Business\Model\Runner\ResultatObnovleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatOshistkiProekta;
use App\Unitman\Business\Model\Runner\ResultatOstanovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSborkiProekta;
use App\Unitman\Business\Model\Runner\ResultatSborkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSbrosaPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaProekta;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatZapuskaUnita;
use App\Unitman\Business\Model\Unit;

interface RunnerService
{
    public function nachatSborkuProekta(Project $project): JobId;

    public function poluchitResultatSborkiProekta(Project $project): ResultatSborkiProekta;

    public function nachatUdalenieProekta(Project $project): JobId;

    public function poluchitResultatUdaleniyaProekta(Project $project): ResultatUdaleniyaProekta;

    public function nachatOchistkuProekta(Project $project): JobId;

    public function poluchitResultatOchistkiProekta(Project $project): ResultatOshistkiProekta;

    public function nachatIzmenenieVetkiUnita(Unit $unit, string $newBranch): JobId;

    public function nachatSborkuUnita(Unit $unit): JobId;

    public function poluchitResultatSborki(Unit $unit): ResultatSborkiUnita;

    public function nachatPodgotovkuUnita(Unit $unit): JobId;

    public function poluchitResultatPodgotovki(Unit $unit): ResultatPodgotovkiUnita;

    public function nachatObnovlenieUnita(Unit $unit): JobId;

    public function poluchitResultatObnovleniyaUnita(Unit $unit): ResultatObnovleniyaUnita;

    public function nachatSbrosPodgotovkiUnita(Unit $unit): JobId;

    public function poluchitResultatSbrosaPodgotovkiUnita(Unit $unit): ResultatSbrosaPodgotovkiUnita;

    public function nachatZapuskUnita(Unit $unit, Project $project): JobId;

    public function nachatVipolnenieDeistviya(Unit $unit, Project $project, string $actionId, array $values): JobId;

    public function poluchitResultatZapuskaUnita(Unit $unit): ResultatZapuskaUnita;

    public function nachatOstanovkuUnita(Unit $unit, Project $project): JobId;

    public function poluchitResultatOstanovkiUnita(Unit $unit): ResultatOstanovkiUnita;

    public function nachatUdalenieUnita(Unit $unit): JobId;

    public function poluchitResultatUdaleniyaUnita(Unit $unit): ResultatUdaleniyaUnita;

    public function poluchitResultatIzmeneniyaVetki(Unit $unit): ResultatIzmeneniyaVetkiUnita;

    public function poluchitResultatDeistviya(Unit $unit): ResultatDeistviyaiUnita;

    public function proveritKonteinerUnita(Unit $unit, Project $project): bool;
}
