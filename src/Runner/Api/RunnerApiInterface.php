<?php

namespace App\Runner\Api;

use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\NachatDeistvieUnita;
use App\Runner\Business\Command\NachatIzmenenieVetkiUnita;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Command\NachatOchistkuProekta;
use App\Runner\Business\Command\NachatOstanovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Command\NachatZapuskUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Model\GolangRunner\Project\InitProjectResult;
use App\Runner\Business\Model\GolangRunner\Project\RemoveProjectResult;
use App\Runner\Business\Model\GolangRunner\Project\ResultatOchistkiProekta;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatDeistviyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatIzmeneniyaVetkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatObnovleniyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatOstanovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrosaPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatUdaleniyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita;

interface RunnerApiInterface
{
    public function initProject(InitProjectCommand $command): string;
    public function poluchitResultatSborkiProekta(string $workflowId): ?InitProjectResult;
    public function removeProject(RemoveProjectCommand $command): string;
    public function poluchitResultatUdaleniyaProekta(string $workflowId): ?RemoveProjectResult;
    public function ochistitProekt(NachatOchistkuProekta $command): string;
    public function poluchitResultatOchistkiProekta(string $workflowId): ?ResultatOchistkiProekta;
    public function nachatSborkuUnita(NachatSborkuUnita $command): string;
    public function poluchitResultatSborki(string $workflowId): ?ResultatSbrokiUnita;
    public function nachatUdalenieUnita(NachatUdalenieUnita $command): string;
    public function poluchitResultatUdaleniyaUnita(string $workflowId): ?ResultatUdaleniyaUnita;
    public function poluchitResultatIzmeneniyaVetki(string $workflowId): ?ResultatIzmeneniyaVetkiUnita;
    public function nachatPodgotovkuUnita(NachatPodgotovkuUnita $command): string;
    public function nachatObnovlenieUnita(NachatObnovlenieUnita $command): string;
    public function nachatIzmenenieVetkiUnita(NachatIzmenenieVetkiUnita $command): string;
    public function nachatSbrosPodgotovkiUnita(NachatSbrosPodgotovkiUnita $command): string;
    public function nachatZapuskUnita(NachatZapuskUnita $command): string;
    public function nachatOstanovkuUnita(NachatOstanovkuUnita $command): string;
    public function nachatDeistvieUnita(NachatDeistvieUnita $command): string;
    public function poluchitResultatPodgotovki(string $workflowId): ?ResultatPodgotovkiUnita;
    public function poluchitResultatObnovleniyaUnita(string $workflowId): ?ResultatObnovleniyaUnita;
    public function poluchitResultatSbrosaPodgotovkiUnita(string $workflowId): ?ResultatSbrosaPodgotovkiUnita;
    public function poluchitResultatZapuskaUnita(string $workflowId): ?ResultatZapuskaUnita;
    public function poluchitResultatOstanovkiUnita(string $workflowId): ?ResultatOstanovkiUnita;

    public function poluchitResultatDeistviya(string $workflowId): ?ResultatDeistviyaUnita;
}
