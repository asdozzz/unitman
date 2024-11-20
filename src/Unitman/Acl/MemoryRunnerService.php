<?php

namespace App\Unitman\Acl;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\InitProjectCommand;
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
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
final class MemoryRunnerService implements RunnerApiInterface
{
    const BUILD_PROJECT = 'BUILD_PROJECT';

    const RESULTAT_SBORKI_PROEKTA = 'RESULTAT_SBORKI_PROEKTA';
    const REMOVE_PROJECT = 'REMOVE_PROJECT';

    const RESULTAT_UDALENIYA_PROEKTA = 'RESULTAT_UDALENIYA_PROEKTA';

    const OCHISTKA_PROEKTA = 'OCHISTKA_PROEKTA';
    const RESULTAT_OCHISTKI_PROEKTA = 'RESULTAT_OCHISTKI_PROEKTA';

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

    const IZMENENIYE_UNITA = 'IZMENENIYE_UNITA';
    const RESULTAT_IZMENENIYA_VETKI = 'RESULTAT_IZMENENIYA_VETKI';
    private array $responses = [];
    public function __construct()
    {
    }

    public function addResponse(string $type, mixed $response, mixed $expectedParams = null): void
    {
        $this->responses[$type][] = ['response' => $response, 'expectedParams' => $expectedParams];
    }

    private function getNextResponse(string $type): mixed
    {
        if (empty($this->responses[$type])) {
            throw new \Exception(sprintf('Response for type=%s not found', $type));
        }

        return array_shift($this->responses[$type]);
    }


    public function initProject(InitProjectCommand $command): string
    {
        $response = $this->getNextResponse(self::BUILD_PROJECT);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function removeProject(RemoveProjectCommand $command): string
    {
        $response = $this->getNextResponse(self::REMOVE_PROJECT);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function ochistitProekt(NachatOchistkuProekta $command): string
    {
        $response = $this->getNextResponse(self::OCHISTKA_PROEKTA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatSborkiProekta(string $workflowId): ?InitProjectResult
    {
        $response = $this->getNextResponse(self::RESULTAT_SBORKI_PROEKTA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatUdaleniyaProekta(string $workflowId): ?RemoveProjectResult
    {
        $response = $this->getNextResponse(self::RESULTAT_UDALENIYA_PROEKTA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatOchistkiProekta(string $workflowId): ?ResultatOchistkiProekta
    {
        $response = $this->getNextResponse(self::RESULTAT_OCHISTKI_PROEKTA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatSborkuUnita(NachatSborkuUnita $command): string
    {
        $response = $this->getNextResponse(self::SBORKA_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatSborki(string $workflowId): ?ResultatSbrokiUnita
    {
        $response = $this->getNextResponse(self::RESULTAT_SBORKI);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatUdalenieUnita(NachatUdalenieUnita $command): string
    {
        $response = $this->getNextResponse(self::UDALENIE_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatUdaleniyaUnita(string $workflowId): ?\App\Runner\Business\Model\GolangRunner\Unit\ResultatUdaleniyaUnita
    {
        $response = $this->getNextResponse(self::RESULTAT_UDALENIYA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatIzmeneniyaVetki(string $workflowId): ?\App\Runner\Business\Model\GolangRunner\Unit\ResultatIzmeneniyaVetkiUnita
    {
        $response = $this->getNextResponse(self::RESULTAT_IZMENENIYA_VETKI);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatPodgotovkuUnita(NachatPodgotovkuUnita $command): string
    {
        $response = $this->getNextResponse(self::PODGOTOVKA_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatObnovlenieUnita(NachatObnovlenieUnita $command): string
    {
        $response = $this->getNextResponse(self::OBNOVLENIE_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatIzmenenieVetkiUnita(NachatIzmenenieVetkiUnita $command): string
    {
        $response = $this->getNextResponse(self::IZMENENIYE_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatSbrosPodgotovkiUnita(NachatSbrosPodgotovkiUnita $command): string
    {
        $response = $this->getNextResponse(self::SBROS_PODGOTOVKI_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatZapuskUnita(NachatZapuskUnita $command): string
    {
        $response = $this->getNextResponse(self::ZAPUSK_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatOstanovkuUnita(NachatOstanovkuUnita $command): string
    {
        $response = $this->getNextResponse(self::OSTANOVKA_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatPodgotovki(string $workflowId): ?\App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita
    {
        $response = $this->getNextResponse(self::RESULTAT_PODGOTOVKI);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatObnovleniyaUnita(string $workflowId): ?\App\Runner\Business\Model\GolangRunner\Unit\ResultatObnovleniyaUnita
    {
        $response = $this->getNextResponse(self::RESULTAT_OBNOVLENIYA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatSbrosaPodgotovkiUnita(string $workflowId): ?\App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrosaPodgotovkiUnita
    {
        $response = $this->getNextResponse(self::RESULTAT_SBROSA_PODGOTOVKI);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatZapuskaUnita(string $workflowId): ?\App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita
    {
        $response = $this->getNextResponse(self::RESULTAT_ZAPUSKA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatOstanovkiUnita(string $workflowId): ?\App\Runner\Business\Model\GolangRunner\Unit\ResultatOstanovkiUnita
    {
        $response = $this->getNextResponse(self::RESULTAT_OSTANOVKI);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }
}
