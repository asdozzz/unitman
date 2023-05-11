<?php

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\RunnerService;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
final class MemoryRunnerService implements RunnerService
{

    const BUILD_PROJECT = 'BUILD_PROJECT';
    const REMOVE_PROJECT = 'REMOVE_PROJECT';
    const SBORKA_UNITA = 'SBORKA_UNITA';
    const PODGOTOVKA_UNITA = 'PODGOTOVKA_UNITA';
    const OBNOVLENIE_UNITA = 'OBNOVLENIE_UNITA';
    const SBROS_PODGOTOVKI_UNITA = 'SBROS_PODGOTOVKI_UNITA';
    const ZAPUSK_UNITA = 'ZAPUSK_UNITA';
    const OSTANOVKA_UNITA = 'OSTANOVKA_UNITA';
    const UDALENIE_UNITA = 'UDALENIE_UNITA';
    private array $responses = [];
    public function __construct()
    {
    }

    public function addResponse(string $type, string $jobId): void
    {
        $this->responses[$type][] = new JobId($jobId);
    }

    private function getNextResponse(string $type): mixed
    {
        if (empty($this->responses[$type])) {
            throw new \Exception(sprintf('Response for type=%s not found', $type));
        }

        return array_shift($this->responses[$type]);
    }
    public function buildProject(Project $project): JobId
    {
        return $this->getNextResponse(self::BUILD_PROJECT);
    }

    public function removeProject(Project $project): JobId
    {
        return $this->getNextResponse(self::REMOVE_PROJECT);
    }

    public function nachatSborkuUnita(Unit $unit): JobId
    {
        return $this->getNextResponse(self::SBORKA_UNITA);
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

    public function nachatZapuskUnita(Unit $unit): JobId
    {
        return $this->getNextResponse(self::ZAPUSK_UNITA);
    }

    public function nachatOstanovkuUnita(Unit $unit): JobId
    {
        return $this->getNextResponse(self::OSTANOVKA_UNITA);
    }

    public function nachatUdalenieUnita(Unit $unit): JobId
    {
        return $this->getNextResponse(self::UDALENIE_UNITA);
    }
}
