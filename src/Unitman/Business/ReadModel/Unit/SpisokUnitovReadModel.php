<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Unit;


final class SpisokUnitovReadModel
{

    /**
     * @param string $id
     * @param string $authorId
     * @param string $authorName
     * @param string $name
     * @param string $projectId
     * @param string $projectName
     * @param string $branch
     * @param array $commands
     * @param array $prozesi
     * @param bool $error
     * @param bool $zapushen
     * @param array $links
     * @param int|null $unixtimePoslednegoObnovleniyaUnita
     * @param int|null $unixtimePoslednegoObnovleniyaVHranilishe
     * @param array $peremenie
     * @param bool $unitSozdanSystemoi
     * @param array $deistviya
     * @param ProjectListContainerStats|null $statistikaKonteinera
     * @param array $volumes
     * @param array $svyazi связи переменных с юнитами, выбранными через хелпер:
     *        `{variableId, targetUnitId, projectId, service, port, suffix}`
     * @param bool $containerStateMismatch признак расхождения фактического и ожидаемого состояния контейнера
     * @param string|null $containerStateMismatchExpected ожидаемое состояние контейнера
     * @param string|null $containerStateMismatchActual фактическое состояние контейнера
     * @param int|null $containerStateMismatchDetectedAt unixtime обнаружения расхождения
     * @param string|null $redaktorLink ссылка на read-only редактор файлов юнита:
     *        появляется после успешной подготовки, очищается при сбросе подготовки
     *        или пересборке; `null` — редактор недоступен
     */
    public function __construct(
        public readonly string $id,
        public readonly string $authorId,
        public readonly string $authorName,
        public readonly string $name,
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $branch,
        public readonly array $commands = [],
        public array $prozesi = [],
        public readonly bool $error = false,
        public readonly bool $zapushen = false,
        public readonly array $links = [],
        public readonly ?int $unixtimePoslednegoObnovleniyaUnita = null,
        public readonly ?int $unixtimePoslednegoObnovleniyaVHranilishe = null,
        public readonly array $peremenie = [],
        public readonly bool $unitSozdanSystemoi = false,
        public readonly array $deistviya = [],
        public readonly ?ProjectListContainerStats $statistikaKonteinera = null,
        public readonly array $volumes = [],
        public readonly array $svyazi = [],
        public readonly bool $containerStateMismatch = false,
        public readonly ?string $containerStateMismatchExpected = null,
        public readonly ?string $containerStateMismatchActual = null,
        public readonly ?int $containerStateMismatchDetectedAt = null,
        public readonly ?string $redaktorLink = null
    )
    {
    }

    private function obnovitProzess(array $inputProzes): array
    {
        $tmp = [];

        foreach ($this->prozesi as $prozes) {
            if ($inputProzes['id'] !== $prozes['id']) {
                $tmp[] = $prozes;
            } else {
                $tmp[] = $inputProzes;
            }
        }

        return $tmp;
    }

    private function udalitProzess(array $inputProzes): array
    {
        $tmp = [];

        foreach ($this->prozesi as $prozes) {
            if ($inputProzes['id'] !== $prozes['id']) {
                $tmp[] = $prozes;
            }
        }

        return $tmp;
    }

    function copyAndUpdateData(array $props): static
    {
        $id = $this->id;
        $authorId = $this->authorId;
        $name = $this->name;
        $projectId = $this->projectId;
        $projectName = $this->projectName;

        $commands = isset($props['commands'])?$props['commands']:$this->commands;
        $links = array_key_exists('links', $props)?$props['links']:$this->links;
        $error = array_key_exists('error', $props)?$props['error']:$this->error;
        $zapushen = array_key_exists('zapushen', $props)?$props['zapushen']:$this->zapushen;
        $branch = array_key_exists('branch', $props)?$props['branch']:$this->branch;
        $unixtimePoslednegoObnovleniyaUnita = isset($props['unixtimePoslednegoObnovleniyaUnita'])?$props['unixtimePoslednegoObnovleniyaUnita']:$this->unixtimePoslednegoObnovleniyaUnita;
        $unixtimePoslednegoObnovleniyaVHranilishe = isset($props['unixtimePoslednegoObnovleniyaVHranilishe'])?$props['unixtimePoslednegoObnovleniyaVHranilishe']:$this->unixtimePoslednegoObnovleniyaVHranilishe;
        $peremenie = !empty($props['peremenie'])?$props['peremenie']:$this->peremenie;
        $unitSozdanSystemoi = isset($props['unitSozdanSystemoi'])?$props['unitSozdanSystemoi']:$this->unitSozdanSystemoi;
        $deistviya = isset($props['deistviya'])?$props['deistviya']:$this->deistviya;
        $statistikaKonteinera = isset($props['statistikaKonteinera'])?$props['statistikaKonteinera']:$this->statistikaKonteinera;
        $volumes = array_key_exists('volumes', $props)?$props['volumes']:$this->volumes;
        // Пустой массив валиден: связи должны очищаться, поэтому !empty использовать нельзя.
        $svyazi = array_key_exists('svyazi', $props)?$props['svyazi']:$this->svyazi;
        // array_key_exists нужен, чтобы явно переданный null очищал ссылку редактора.
        $redaktorLink = array_key_exists('redaktorLink', $props)
            ? ($props['redaktorLink'] === null ? null : (string) $props['redaktorLink'])
            : $this->redaktorLink;

        $containerStateMismatch = array_key_exists('containerStateMismatch', $props)
            ? (bool) $props['containerStateMismatch']
            : $this->containerStateMismatch;
        $containerStateMismatchExpected = null;
        $containerStateMismatchActual = null;
        $containerStateMismatchDetectedAt = null;

        if ($containerStateMismatch) {
            $expected = array_key_exists('containerStateMismatchExpected', $props)
                ? $props['containerStateMismatchExpected']
                : $this->containerStateMismatchExpected;
            $containerStateMismatchExpected = $expected === null ? null : (string) $expected;

            $actual = array_key_exists('containerStateMismatchActual', $props)
                ? $props['containerStateMismatchActual']
                : $this->containerStateMismatchActual;
            $containerStateMismatchActual = $actual === null ? null : (string) $actual;

            $detectedAt = array_key_exists('containerStateMismatchDetectedAt', $props)
                ? $props['containerStateMismatchDetectedAt']
                : $this->containerStateMismatchDetectedAt;
            $containerStateMismatchDetectedAt = $detectedAt === null ? null : (int) $detectedAt;
        }

        if (isset($props['prozesi'])) {
            $prozesi = $props['prozesi'];
        } else if (isset($props['prozes'])) {
            $prozesi = $this->obnovitProzess($props['prozes']);
        } else {
            $prozesi = $this->prozesi;
        }

        return new static(
            $id,
            $authorId,
            $this->authorName,
            $name,
            $projectId,
            $projectName,
            $branch,
            $commands,
            $prozesi,
            $error,
            $zapushen,
            $links,
            $unixtimePoslednegoObnovleniyaUnita,
            $unixtimePoslednegoObnovleniyaVHranilishe,
            $peremenie,
            $unitSozdanSystemoi,
            $deistviya,
            $statistikaKonteinera,
            $volumes,
            $svyazi,
            $containerStateMismatch,
            $containerStateMismatchExpected,
            $containerStateMismatchActual,
            $containerStateMismatchDetectedAt,
            $redaktorLink
        );
    }
}
