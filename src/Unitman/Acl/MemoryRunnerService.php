<?php

namespace App\Unitman\Acl;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\NachatDeistvieUnita;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Command\NachatOstanovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Command\NachatZapuskUnita;
use App\Runner\Business\Command\ProveritKonteinerUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Command\Workspace\OstanovitWorkspace;
use App\Runner\Business\Command\Workspace\ProveritIzmeneniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatOstanovkiWorkspace;
use App\Runner\Business\Command\Workspace\ResultatProverkiIzmeneniyWorkspace;
use App\Runner\Business\Command\Workspace\ResultatUdaleniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatZapuskaWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use App\Runner\Business\Model\GolangRunner\OpenCode\ResultatHodaOpenCode;
use App\Runner\Business\Model\GolangRunner\Project\InitProjectResult;
use App\Runner\Business\Model\GolangRunner\Project\RemoveProjectResult;
use App\Runner\Business\Model\GolangRunner\Project\ResultatOchistkiProekta;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatDeistviyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatProverkiKonteineraUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\RunnerProgress;
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

    const DEISTVIE_UNITA = 'DEISTVIE_UNITA';
    const RESULTAT_IZMENENIYA_VETKI = 'RESULTAT_IZMENENIYA_VETKI';

    const RESULTAT_DEISTVIYA = 'RESULTAT_DEISTVIYA';

    const PROVERKA_UNITA = 'PROVERKA_UNITA';

    const PROGRESS_ZADACHI = 'PROGRESS_ZADACHI';

    const HOD_OPENCODE = 'HOD_OPENCODE';
    const RESULTAT_HODA_OPENCODE = 'RESULTAT_HODA_OPENCODE';

    const ZAPUSK_WORKSPACE = 'ZAPUSK_WORKSPACE';
    const RESULTAT_ZAPUSKA_WORKSPACE = 'RESULTAT_ZAPUSKA_WORKSPACE';
    const OSTANOVKA_WORKSPACE = 'OSTANOVKA_WORKSPACE';
    const RESULTAT_OSTANOVKI_WORKSPACE = 'RESULTAT_OSTANOVKI_WORKSPACE';
    const UDALENIE_WORKSPACE = 'UDALENIE_WORKSPACE';
    const RESULTAT_UDALENIYA_WORKSPACE = 'RESULTAT_UDALENIYA_WORKSPACE';
    const PROVERKA_IZMENENIY_WORKSPACE = 'PROVERKA_IZMENENIY_WORKSPACE';

    /** @var array<string, list<array{response: mixed, expectedParams: mixed}>> */
    private array $responses = [];

    /** @var array<string, ZapustitWorkspaceZapros> */
    private array $workspaceCommands = [];

    /** @var list<array{method: string, args: list<mixed>}> */
    private array $calls = [];

    public function __construct()
    {
    }

    public function addResponse(string $type, mixed $response, mixed $expectedParams = null): void
    {
        $this->responses[$type][] = ['response' => $response, 'expectedParams' => $expectedParams];
    }

    /**
     * Журнал вызовов workspace-методов — для проверок в тестах.
     *
     * @return list<array{method: string, args: list<mixed>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
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

    public function nachatSborkuUnita(string $workflowId,NachatSborkuUnita $command): string
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

    public function nachatUdalenieUnita(string $workflowId,NachatUdalenieUnita $command): string
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

    public function nachatPodgotovkuUnita(string $workflowId,NachatPodgotovkuUnita $command): string
    {
        $response = $this->getNextResponse(self::PODGOTOVKA_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatObnovlenieUnita(string $workflowId,NachatObnovlenieUnita $command): string
    {
        $response = $this->getNextResponse(self::OBNOVLENIE_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatSbrosPodgotovkiUnita(string $workflowId,NachatSbrosPodgotovkiUnita $command): string
    {
        $response = $this->getNextResponse(self::SBROS_PODGOTOVKI_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatZapuskUnita(string $workflowId,NachatZapuskUnita $command): string
    {
        $response = $this->getNextResponse(self::ZAPUSK_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatOstanovkuUnita(string $workflowId,NachatOstanovkuUnita $command): string
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

    public function nachatDeistvieUnita(string $workflowId,NachatDeistvieUnita $command): string
    {
        $response = $this->getNextResponse(self::DEISTVIE_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatDeistviya(string $workflowId): ?ResultatDeistviyaUnita
    {
        $response = $this->getNextResponse(self::RESULTAT_DEISTVIYA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function zaregistrirovatProgressToken(string $workflowId): void
    {
    }

    public function poluchitProgressZadachi(string $workflowId): ?RunnerProgress
    {
        return $this->getNextResponse(self::PROGRESS_ZADACHI);
    }

    /**
     * Формат имени канала повторяет `RunnerProgressPublisher::CHANNEL_PREFIX`.
     *
     * Литерал, а не константа, потому что Unitman не имеет права тянуть
     * `Runner\Infra` — двойник существует только для тестов, где важна форма
     * ответа, а не источник истины по имени канала.
     */
    public function poluchitKanalProgressa(string $workflowId): string
    {
        return 'unit_progress#' . $workflowId;
    }

    public function proveritKonteinerUnita(ProveritKonteinerUnita $command): ?ResultatProverkiKonteineraUnita
    {
        $response = $this->getNextResponse(self::PROVERKA_UNITA);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function nachatHodOpenCode(string $workflowId, NachatHodOpenCode $command): string
    {
        $response = $this->getNextResponse(self::HOD_OPENCODE);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function poluchitResultatHodaOpenCode(string $workflowId): ?ResultatHodaOpenCode
    {
        $response = $this->getNextResponse(self::RESULTAT_HODA_OPENCODE);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }
        return $response['response'];
    }

    public function zapustitWorkspace(string $workflowId, ZapustitWorkspaceZapros $command): string
    {
        $this->workspaceCommands[$workflowId] = $command;
        $this->calls[] = ['method' => 'zapustitWorkspace', 'args' => [$workflowId, $command]];

        if (empty($this->responses[self::ZAPUSK_WORKSPACE])) {
            return $workflowId;
        }

        $response = $this->getNextResponse(self::ZAPUSK_WORKSPACE);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }

        return $response['response'];
    }

    public function poluchitResultatZapuskaWorkspace(string $workflowId): ?ResultatZapuskaWorkspace
    {
        $this->calls[] = ['method' => 'poluchitResultatZapuskaWorkspace', 'args' => [$workflowId]];

        if (!empty($this->responses[self::RESULTAT_ZAPUSKA_WORKSPACE])) {
            $response = $this->getNextResponse(self::RESULTAT_ZAPUSKA_WORKSPACE);
            if (!empty($response['expectedParams'])) {
                $response['expectedParams'](func_get_args());
            }

            /** @var ResultatZapuskaWorkspace $result */
            $result = $response['response'];

            return $result;
        }

        $projects = [];
        $command = $this->workspaceCommands[$workflowId] ?? null;
        if ($command !== null) {
            foreach ($command->Projects as $project) {
                $projects[] = [
                    'ProjectId' => $project->ProjectId,
                    'Name' => $project->ProjectName,
                    'Success' => true,
                    'ErrorKey' => null,
                ];
            }
        }

        return new ResultatZapuskaWorkspace(1, [], $workflowId, $projects);
    }

    public function ostanovitWorkspace(string $workflowId, OstanovitWorkspace $command): string
    {
        $this->calls[] = ['method' => 'ostanovitWorkspace', 'args' => [$workflowId, $command]];

        if (empty($this->responses[self::OSTANOVKA_WORKSPACE])) {
            return $workflowId;
        }

        $response = $this->getNextResponse(self::OSTANOVKA_WORKSPACE);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }

        return $response['response'];
    }

    public function poluchitResultatOstanovkiWorkspace(string $workflowId): ?ResultatOstanovkiWorkspace
    {
        $this->calls[] = ['method' => 'poluchitResultatOstanovkiWorkspace', 'args' => [$workflowId]];

        if (!empty($this->responses[self::RESULTAT_OSTANOVKI_WORKSPACE])) {
            $response = $this->getNextResponse(self::RESULTAT_OSTANOVKI_WORKSPACE);
            if (!empty($response['expectedParams'])) {
                $response['expectedParams'](func_get_args());
            }

            /** @var ResultatOstanovkiWorkspace $result */
            $result = $response['response'];

            return $result;
        }

        return new ResultatOstanovkiWorkspace(1, [], $workflowId);
    }

    public function udalitWorkspace(string $workflowId, UdalitWorkspace $command): string
    {
        $this->calls[] = ['method' => 'udalitWorkspace', 'args' => [$workflowId, $command]];

        if (empty($this->responses[self::UDALENIE_WORKSPACE])) {
            return $workflowId;
        }

        $response = $this->getNextResponse(self::UDALENIE_WORKSPACE);
        if (!empty($response['expectedParams'])) {
            $response['expectedParams'](func_get_args());
        }

        return $response['response'];
    }

    public function poluchitResultatUdaleniyaWorkspace(string $workflowId): ?ResultatUdaleniyaWorkspace
    {
        $this->calls[] = ['method' => 'poluchitResultatUdaleniyaWorkspace', 'args' => [$workflowId]];

        if (!empty($this->responses[self::RESULTAT_UDALENIYA_WORKSPACE])) {
            $response = $this->getNextResponse(self::RESULTAT_UDALENIYA_WORKSPACE);
            if (!empty($response['expectedParams'])) {
                $response['expectedParams'](func_get_args());
            }

            /** @var ResultatUdaleniyaWorkspace $result */
            $result = $response['response'];

            return $result;
        }

        return new ResultatUdaleniyaWorkspace(1, [], $workflowId);
    }

    public function proveritIzmeneniyaWorkspace(
        ProveritIzmeneniyaWorkspace $command,
    ): ResultatProverkiIzmeneniyWorkspace {
        $this->calls[] = ['method' => 'proveritIzmeneniyaWorkspace', 'args' => [$command]];

        if (!empty($this->responses[self::PROVERKA_IZMENENIY_WORKSPACE])) {
            $response = $this->getNextResponse(self::PROVERKA_IZMENENIY_WORKSPACE);
            if (!empty($response['expectedParams'])) {
                $response['expectedParams'](func_get_args());
            }

            /** @var ResultatProverkiIzmeneniyWorkspace $result */
            $result = $response['response'];

            return $result;
        }

        $projects = [];
        foreach ($command->Projects as $project) {
            $projects[] = [
                'ProjectId' => $project['ProjectId'],
                'Name' => $project['ProjectName'],
                'Dirty' => false,
                'Files' => [],
            ];
        }

        return new ResultatProverkiIzmeneniyWorkspace(true, $projects);
    }
}
