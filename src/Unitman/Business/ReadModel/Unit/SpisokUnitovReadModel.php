<?php

namespace App\Unitman\Business\ReadModel\Unit;

use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessState;

final class SpisokUnitovReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $authorId,
        public readonly string $authorName,
        public readonly string $name,
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $branch,
        public readonly array $commands = [],
        public readonly array $prozesi = [],
        public readonly bool $error = false,
        public readonly array $links = [],
        public readonly ?int $unixtimePoslednegoObnovleniyaUnita = null,
        public readonly ?int $unixtimePoslednegoObnovleniyaVHranilishe = null,
        public readonly array $peremenie = [],
        public readonly bool $unitSozdanSystemoi = false,
        public readonly array $deistviya = [],
        public readonly ?ProjectListContainerStats $statistikaKonteinera = null
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
        $branch = array_key_exists('branch', $props)?$props['branch']:$this->branch;
        $unixtimePoslednegoObnovleniyaUnita = isset($props['unixtimePoslednegoObnovleniyaUnita'])?$props['unixtimePoslednegoObnovleniyaUnita']:$this->unixtimePoslednegoObnovleniyaUnita;
        $unixtimePoslednegoObnovleniyaVHranilishe = isset($props['unixtimePoslednegoObnovleniyaVHranilishe'])?$props['unixtimePoslednegoObnovleniyaVHranilishe']:$this->unixtimePoslednegoObnovleniyaVHranilishe;
        $peremenie = !empty($props['peremenie'])?$props['peremenie']:$this->peremenie;
        $unitSozdanSystemoi = isset($props['unitSozdanSystemoi'])?$props['unitSozdanSystemoi']:$this->unitSozdanSystemoi;
        $deistviya = isset($props['deistviya'])?$props['deistviya']:$this->deistviya;
        $statistikaKonteinera = isset($props['statistikaKonteinera'])?$props['statistikaKonteinera']:$this->statistikaKonteinera;

        if (isset($props['prozesi'])) {
            $prozesi = $props['prozesi'];
        } else if (isset($props['prozes'])) {
            if ($props['prozes']['state'] == UnitProcessState::SUCCESS->value || $props['prozes']['state'] == UnitProcessState::ERROR->value) {
                $prozesi = $this->udalitProzess($props['prozes']);
            } else {
                $prozesi = $this->obnovitProzess($props['prozes']);
            }
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
            $links,
            $unixtimePoslednegoObnovleniyaUnita,
            $unixtimePoslednegoObnovleniyaVHranilishe,
            $peremenie,
            $unitSozdanSystemoi,
            $deistviya,
            $statistikaKonteinera
        );
    }
}
