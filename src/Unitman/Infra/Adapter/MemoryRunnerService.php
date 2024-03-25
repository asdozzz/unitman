<?php

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResultatObnovleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatOstanovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSborkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSbrosaPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatZapuskaUnita;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\RunnerService;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
final class MemoryRunnerService implements RunnerService
{
    const BUILD_PROJECT = 'BUILD_PROJECT';
    const REMOVE_PROJECT = 'REMOVE_PROJECT';
    const SBORKA_UNITA = 'SBORKA_UNITA';
    const RESULTAT_SBORKI = 'RESULTAT_SBORKI';
    const PODGOTOVKA_UNITA = 'PODGOTOVKA_UNITA';
    const RESULTAT_PODGOTOVKI = 'RESULTAT_PODGOTOVKI';
    const OBNOVLENIE_UNITA = 'OBNOVLENIE_UNITA';
    const RESULTAT_OBNOVLENIYA = 'RESULTAT_OBNOVLENIYA';
    const SBROS_PODGOTOVKI_UNITA = 'SBROS_PODGOTOVKI_UNITA';
    const RESULTAT_SBROSA_PODGOTOVKI = 'RESULTAT_SBROSA_PODGOTOVKI';
    const ZAPUSK_UNITA = 'ZAPUSK_UNITA';
    const RESULTAT_ZAPUSKA = 'RESULTAT_ZAPUSKA';
    const OSTANOVKA_UNITA = 'OSTANOVKA_UNITA';
    const RESULTAT_OSTANOVKI = 'RESULTAT_OSTANOVKI';
    const UDALENIE_UNITA = 'UDALENIE_UNITA';
    const RESULTAT_UDALENIYA = 'RESULTAT_UDALENIYA';
    private array $responses = [];
    public function __construct()
    {
    }

    public function addResponse(string $type, mixed $response): void
    {
        $this->responses[$type][] = $response;
    }

    private function getNextResponse(string $type): mixed
    {
        if (empty($this->responses[$type])) {
            throw new \Exception(sprintf('Response for type=%s not found', $type));
        }

        return array_shift($this->responses[$type]);
    }
    public function buildProject(Project $project): Project\ProjectDataAboutBuilding
    {
        return $this->getNextResponse(self::BUILD_PROJECT);
    }

    public function removeProject(Project $project): Project\ProjectDataAboutRemoving
    {
        return $this->getNextResponse(self::REMOVE_PROJECT);
    }

    public function nachatSborkuUnita(Unit $unit): JobId
    {
        return $this->getNextResponse(self::SBORKA_UNITA);
    }

    public function poluchitResultatSborki(Unit $unit): ResultatSborkiUnita
    {
        return $this->getNextResponse(self::RESULTAT_SBORKI);
    }

    public function nachatPodgotovkuUnita(Unit $unit): JobId
    {
        return $this->getNextResponse(self::PODGOTOVKA_UNITA);
    }

    public function nachatObnovlenieUnita(Unit $unit): JobId
    {
        return $this->getNextResponse(self::OBNOVLENIE_UNITA);
    }

    public function nachatSbrosPodgotovkiUnita(Unit $unit): JobId
    {
        return $this->getNextResponse(self::SBROS_PODGOTOVKI_UNITA);
    }

    public function nachatZapuskUnita(Unit $unit, Project $project): JobId
    {
        return $this->getNextResponse(self::ZAPUSK_UNITA);
    }

    public function nachatOstanovkuUnita(Unit $unit, Project $project): JobId
    {
        return $this->getNextResponse(self::OSTANOVKA_UNITA);
    }

    public function nachatUdalenieUnita(Unit $unit): JobId
    {
        return $this->getNextResponse(self::UDALENIE_UNITA);
    }

    public function poluchitResultatPodgotovki(Unit $unit): ResultatPodgotovkiUnita
    {
        return $this->getNextResponse(self::RESULTAT_PODGOTOVKI);
    }

    public function poluchitResultatObnovleniyaUnita(Unit $unit): ResultatObnovleniyaUnita
    {
        return $this->getNextResponse(self::RESULTAT_OBNOVLENIYA);
    }

    public function poluchitResultatSbrosaPodgotovkiUnita(Unit $unit): ResultatSbrosaPodgotovkiUnita
    {
        return $this->getNextResponse(self::RESULTAT_SBROSA_PODGOTOVKI);
    }

    public function poluchitResultatZapuskaUnita(Unit $unit): ResultatZapuskaUnita
    {
        return $this->getNextResponse(self::RESULTAT_ZAPUSKA);
    }

    public function poluchitResultatOstanovkiUnita(Unit $unit): ResultatOstanovkiUnita
    {
        return $this->getNextResponse(self::RESULTAT_OSTANOVKI);
    }

    public function poluchitResultatUdaleniyaUnita(Unit $unit): ResultatUdaleniyaUnita
    {
        return $this->getNextResponse(self::RESULTAT_UDALENIYA);
    }
}
