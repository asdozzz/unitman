<?php

namespace App\Unitman\Business\ReadModel\Unit;

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
        public readonly string $state,
        public readonly bool $waitResultFromRunner,
        public readonly array $commands = [],
        public readonly bool $error = false,
        public readonly array $links = [],
        public readonly bool $jdemObnovlenieKodaPosleZapuska = false,
        public readonly bool $jdemUdaleniyaPosleZapuska = false,
        public readonly ?int $unixtimePoslednegoObnovleniyaUnita = null,
        public readonly ?int $unixtimePoslednegoObnovleniyaVHranilishe = null,
        public readonly array $peremenie = [],
        public readonly bool $unitSozdanSystemoi = false,
        public readonly bool $jdemAvtosborki = false,
        public readonly array $deistviya = []
    )
    {
    }

    function copyAndUpdateData(array $props): static
    {
        $id = $this->id;
        $authorId = $this->authorId;
        $name = $this->name;
        $projectId = $this->projectId;
        $projectName = $this->projectName;

        $state = $props['state']??$this->state;
        $waitResultFromRunner = isset($props['waitResultFromRunner'])?$props['waitResultFromRunner']:$this->waitResultFromRunner;
        $commands = isset($props['commands'])?$props['commands']:$this->commands;
        $links = array_key_exists('links', $props)?$props['links']:$this->links;
        $error = array_key_exists('error', $props)?$props['error']:$this->error;
        $branch = array_key_exists('branch', $props)?$props['branch']:$this->branch;
        $jdemObnovlenieKodaPosleZapuska = isset($props['jdemObnovlenieKodaPosleZapuska'])?$props['jdemObnovlenieKodaPosleZapuska']:$this->jdemObnovlenieKodaPosleZapuska;
        $jdemUdaleniyaPosleZapuska = isset($props['jdemUdaleniyaPosleZapuska'])?$props['jdemUdaleniyaPosleZapuska']:$this->jdemUdaleniyaPosleZapuska;
        $unixtimePoslednegoObnovleniyaUnita = isset($props['unixtimePoslednegoObnovleniyaUnita'])?$props['unixtimePoslednegoObnovleniyaUnita']:$this->unixtimePoslednegoObnovleniyaUnita;
        $unixtimePoslednegoObnovleniyaVHranilishe = isset($props['unixtimePoslednegoObnovleniyaVHranilishe'])?$props['unixtimePoslednegoObnovleniyaVHranilishe']:$this->unixtimePoslednegoObnovleniyaVHranilishe;
        $peremenie = !empty($props['peremenie'])?$props['peremenie']:$this->peremenie;
        $unitSozdanSystemoi = isset($props['unitSozdanSystemoi'])?$props['unitSozdanSystemoi']:$this->unitSozdanSystemoi;
        $jdemAvtosborki = isset($props['jdemAvtosborki'])?$props['jdemAvtosborki']:$this->jdemAvtosborki;
        $deistviya = isset($props['deistviya'])?$props['deistviya']:$this->deistviya;

        return new static(
            $id,
            $authorId,
            $this->authorName,
            $name,
            $projectId,
            $projectName,
            $branch,
            $state,
            $waitResultFromRunner,
            $commands,
            $error,
            $links,
            $jdemObnovlenieKodaPosleZapuska,
            $jdemUdaleniyaPosleZapuska,
            $unixtimePoslednegoObnovleniyaUnita,
            $unixtimePoslednegoObnovleniyaVHranilishe,
            $peremenie,
            $unitSozdanSystemoi,
            $jdemAvtosborki,
            $deistviya
        );
    }
}
