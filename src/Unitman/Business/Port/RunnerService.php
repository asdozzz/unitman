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

    public function nachatSborkuUnita(string $jobId, Unit $unit): JobId;

    public function poluchitResultatSborki(string $jobId): ResultatSborkiUnita;

    public function nachatPodgotovkuUnita(string $jobId,Unit $unit): JobId;

    public function poluchitResultatPodgotovki(string $jobId): ResultatPodgotovkiUnita;

    public function nachatObnovlenieUnita(string $jobId,Unit $unit): JobId;

    public function poluchitResultatObnovleniyaUnita(string $jobId): ResultatObnovleniyaUnita;

    public function nachatSbrosPodgotovkiUnita(string $jobId, Unit $unit): JobId;

    public function poluchitResultatSbrosaPodgotovkiUnita(string $jobId): ResultatSbrosaPodgotovkiUnita;

    public function nachatZapuskUnita(string $jobId, Unit $unit, Project $project): JobId;

    public function nachatVipolnenieDeistviya(string $jobId, Unit $unit, Project $project, string $actionId, array $values): JobId;

    public function poluchitResultatZapuskaUnita(string $jobId): ResultatZapuskaUnita;

    public function nachatOstanovkuUnita(string $jobId, Unit $unit, Project $project): JobId;

    public function poluchitResultatOstanovkiUnita(string $jobId): ResultatOstanovkiUnita;

    public function nachatUdalenieUnita(string $jobId, Unit $unit): JobId;

    public function poluchitResultatUdaleniyaUnita(string $jobId): ResultatUdaleniyaUnita;

    public function poluchitResultatDeistviya(string $jobId): ResultatDeistviyaiUnita;

    public function proveritKonteinerUnita(Unit $unit, Project $project): bool;
}
