<?php

namespace App\Runner\Acl;

use App\Runner\Business\Model\RepoCredentialGrant;
use App\Runner\Business\Model\RunnerState\DockerContainerStats;
use App\Runner\Business\Port\UmeetObnovlyatSostoyanieKonteyneraUnita;
use App\Runner\Business\Port\UmeetObnovlyatStatistikuPoUnitam;
use App\Runner\Business\Port\UmeetPoluchatKredensialyRepozitoriya;
use App\Runner\Business\Port\UmeetPoluchatUrlKlonaProekta;
use App\Unitman\Api\UnitmanApi;
use App\Unitman\Business\Command\Unit\ObnovitSostoyanieKonteyneraUnita;
use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;

final class UnitmanAdapter implements
    UmeetObnovlyatStatistikuPoUnitam,
    UmeetObnovlyatSostoyanieKonteyneraUnita,
    UmeetPoluchatUrlKlonaProekta,
    UmeetPoluchatKredensialyRepozitoriya
{
    public function __construct(private UnitmanApi $unitmanApi)
    {
    }

    public function poluchitUrlKlona(string $projectId): string
    {
        return $this->unitmanApi->poluchitUrlKlonaProekta($projectId);
    }

    public function poluchitRepoIdKlona(string $projectId): string
    {
        return $this->unitmanApi->poluchitRepoIdProekta($projectId);
    }

    public function poluchitKredensialy(
        string $runnerId,
        string $repoId,
        string $projectId,
        ?string $unitId = null,
    ): RepoCredentialGrant {
        $grant = $this->unitmanApi->poluchitKredensialyRepozitoriya(
            $runnerId,
            $repoId,
            $projectId,
            $unitId,
        );

        return new RepoCredentialGrant(
            $grant->repoId,
            $grant->projectId,
            $grant->repoUrl,
            $grant->username,
            $grant->token,
            $grant->expiresAt,
        );
    }

    /**
     * @param DockerContainerStats[] $containerStats
     * */
    public function obnovitStatistikuPoUnitam(array $containerStats): void
    {
        if (empty($containerStats)) {
            return;
        }
        foreach ($containerStats as $containerStat) {
            $command = new ObnovitStatistikuPoKontaineruUnita(
                containerName: $containerStat->containerName,
                cpuPercent: $containerStat->cpuPercent,
                memoryPercent: $containerStat->memoryPercent,
                memoryUsage: $containerStat->memoryUsage,
                netIO: $containerStat->netIO,
            );
            $this->unitmanApi->obnovitStatistikuUnita($command);
        }
    }

    public function obnovit(
        string $unitId,
        bool $mismatch,
        ?string $expectedState,
        ?string $actualState,
        ?int $detectedAt,
    ): void {
        $this->unitmanApi->obnovitSostoyanieKonteyneraUnita(
            new ObnovitSostoyanieKonteyneraUnita(
                unitId: $unitId,
                mismatch: $mismatch,
                expectedState: $expectedState,
                actualState: $actualState,
                detectedAt: $detectedAt,
            ),
        );
    }
}
