<?php

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuObnovleniyaUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuPriPodgotovkeUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuSbrosaPodgotovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuUdaleniya;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuZapuska;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Unit\ConfigUnita;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\OshibkaObnovleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaOstanovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSborkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSbrosaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaUdaleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaZapuskaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OstanovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\PeremenieUnitaZapolneni;
use App\Unitman\Business\Model\Unit\Event\PodgotovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\SborkaUnitNachalas;
use App\Unitman\Business\Model\Unit\Event\SbrosPodgotovkiNachalsya;
use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UspehObnovleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehOstanovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSborkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSbrosaPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehZapuskaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ZapuskUnitNachalsya;
use App\Unitman\Business\Model\Unit\RunnerJob;
use App\Unitman\Business\Model\Unit\State\AbstractState;
use App\Unitman\Business\Model\Unit\State\OshibkaObnovleniya;
use App\Unitman\Business\Model\Unit\State\OshibkaOstanovki;
use App\Unitman\Business\Model\Unit\State\OshibkaPodgotovki;
use App\Unitman\Business\Model\Unit\State\OshibkaSborki;
use App\Unitman\Business\Model\Unit\State\OshibkaSbrosaPodgotovki;
use App\Unitman\Business\Model\Unit\State\OshibkaZapuska;
use App\Unitman\Business\Model\Unit\State\Podgotovlen;
use App\Unitman\Business\Model\Unit\State\Sloman;
use App\Unitman\Business\Model\Unit\State\Sobran;
use App\Unitman\Business\Model\Unit\State\Sozdan;
use App\Unitman\Business\Model\Unit\State\StateFactory;
use App\Unitman\Business\Model\Unit\State\Udalen;
use App\Unitman\Business\Model\Unit\State\UdalenVruchnuyu;
use App\Unitman\Business\Model\Unit\State\VOcherediNaObnovlenie;
use App\Unitman\Business\Model\Unit\State\VOcherediNaOstanovku;
use App\Unitman\Business\Model\Unit\State\VOcherediNaPodgotovku;
use App\Unitman\Business\Model\Unit\State\VOcherediNaSborku;
use App\Unitman\Business\Model\Unit\State\VOcherediNaSbrosPodgotovki;
use App\Unitman\Business\Model\Unit\State\VOcherediNaUdalenie;
use App\Unitman\Business\Model\Unit\State\VOcherediNaZapusk;
use App\Unitman\Business\Model\Unit\State\Zapushen;
use App\Unitman\Business\Model\Unit\UnitBranch;
use App\Unitman\Business\Model\Unit\UnitId;
use App\Unitman\Business\Model\Unit\UnitName;
use App\Unitman\Business\Model\Unit\UnitProject;
use App\Unitman\Business\Model\Unit\VariableValue;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

final class Unit implements AggregateRoot
{
    private ?UnitName $name;
    private ?UnitBranch $branch;
    private ?UnitProject $project;
    private string $authorId;

    private ?ConfigUnita $configUnita = null;

    private array $variableValues = [];
    private ?RunnerJob $sborka = null;
    private ?RunnerJob $podgotovka = null;
    private ?RunnerJob $obnovlenie = null;
    private ?RunnerJob $sbrosPodgotovki = null;
    private ?RunnerJob $zapusk = null;
    private ?RunnerJob $ostanovka = null;
    private ?RunnerJob $udalenie = null;

    private ?AbstractState $state = null;

    private bool $isDeleted = false;
    /**
     * @template-use AggregateRootBehaviour<UnitId>
     * */
    use AggregateRootBehaviour;

    public function getId(): string
    {
        return $this->aggregateRootId->toString();
    }

    function esliZapushen(): bool
    {
        return !empty($this->zapusk) && $this->zapusk->isSuccess();
    }
    function esliPodgotovlen(): bool
    {
        return !empty($this->podgotovka) && $this->podgotovka->isSuccess();
    }

    public function isWaitResultFromRunner(): bool
    {
        $props = [
            'sborka',
            'podgotovka',
            'obnovlenie',
            'sbrosPodgotovki',
            'zapusk',
            'ostanovka',
            'udalenie',
        ];

        foreach ($props as $prop) {
            if (!empty($this->{$prop}) && !$this->{$prop}->isFinish()) {
                return true;
            }
        }

        return false;
    }

    public function esliRazreshenoUpravlyatUnitom(string $userId): bool
    {
        return $this->authorId === $userId;
    }
    public static function sozdatUnit(string $id, string $authorId, string $projectName, SozdatUnit $command): static
    {
        if (empty($command->projectId)) {
            throw new \DomainException('unit.projectId_is_empty');
        }
        if (empty($authorId)) {
            throw new \DomainException('unit.authorId_is_empty');
        }
        $unit = new static(UnitId::fromString($id));
        $state = new Sozdan();
        $unit->recordThat(new UnitSozdan($id, $authorId, $command->projectId, $projectName, $command->unitName, $command->branch, $state->toArray($unit)));
        return $unit;
    }

    /**
     * @param array $values
     * @return array
     */
    private function makeVariableCollectionByArray(array $values): array
    {
        $tmpVariables = [];
        foreach ($values as $id => $value) {
            $tmpVariables[] = new VariableValue($id, $value);
        }
        return $tmpVariables;
    }

    private function applyUnitSozdan(UnitSozdan $fact): void
    {
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->project = new UnitProject($fact->projectId, $fact->projectName);
        $this->name = new UnitName($fact->name);
        $this->branch = new UnitBranch($fact->branch);
        $this->authorId = $fact->authorId;
    }

    public function nachatSborkuUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if (!empty($this->sborka)) {
            throw new \DomainException('unit.uge_sobran');
        }
        $state = $this->state->newState(new VOcherediNaSborku());
        $this->recordThat(new SborkaUnitNachalas($this->getId(), $jobId, $state->toArray($this)));
    }

    private function applySborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $this->sborka = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuSborki(UstanovitOshibkuSborkiUnita $command): void
    {
        if (empty($this->sborka)) {
            throw new \DomainException('unit.sborka_ne_nachalas');
        }

        if ($this->sborka->isFinish()) {
            throw new \DomainException('unit.resultat_sborki_uge_ustanovlen');
        }
        $state = $this->state->newState(new OshibkaSborki());
        $this->recordThat(new OshibkaSborkiUnitaUstanovlena($command->unitId, $command->textOtRunnera, $state->toArray($this)));
    }

    private function applyOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $this->sborka = $this->sborka->ustanovitOshibku($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehSborki(string $textOtRunnera, array $configUnita): void
    {
        if (empty($this->sborka)) {
            throw new \DomainException('unit.sborka_ne_nachalas');
        }

        if ($this->sborka->isFinish()) {
            throw new \DomainException('unit.resultat_sborki_uge_ustanovlen');
        }
        $state = $this->state->newState(new Sobran());
        $this->recordThat(new UspehSborkiUnitaUstanovlen($this->getId(), $textOtRunnera, $configUnita, $state->toArray($this)));
    }

    private function applyUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $this->sborka = $this->sborka->ustanovitUspeh($fact->textOtRunnera);
        if (!empty($fact->configUnita)) {
            $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
        }
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function zapolnitPeremenie(array $values)
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }

        if (empty($this->configUnita)) {
            throw new \DomainException('unit.config_unita_ne_opredelen');
        }

        $tmpVariables = $this->makeVariableCollectionByArray($values);

        $errs = $this->configUnita->validateValues($tmpVariables);
        if (!empty($errs)) {
            throw new \DomainException(join(', ', $errs));
        }

        if ($this->esliZapushen()) {
            $state = $this->state->newState(new Zapushen());
        } elseif ($this->esliPodgotovlen()) {
            $state = $this->state->newState(new Podgotovlen());
        } else {
            $state = $this->state->newState(new Sobran());
        }

        $this->recordThat(new PeremenieUnitaZapolneni($this->getId(), $values, $state->toArray($this)));
    }

    private function applyPeremenieUnitaZapolneni(PeremenieUnitaZapolneni $fact): void
    {
        $this->variableValues = $this->makeVariableCollectionByArray($fact->values);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function nachatPodgotovkuUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }

        if (!empty($this->podgotovka) && $this->podgotovka->isSuccess()) {
            throw new \DomainException('unit.podgotovka_uge_bila');
        }

        if (empty($this->sborka)) {
            throw new \DomainException('unit.sborka_ne_nachalas');
        }

        if (empty($this->configUnita)) {
            throw new \DomainException('unit.config_unita_ne_opredelen');
        }

        $errs = $this->configUnita->validateValues($this->variableValues);
        if (!empty($errs)) {
            throw new \DomainException(join(', ', $errs));
        }
        $state = $this->state->newState(new VOcherediNaPodgotovku());
        $this->recordThat(new PodgotovkaUnitaNachalas($this->getId(), $jobId, $state->toArray($this)));
    }

    private function applyPodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $this->podgotovka = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuPodgotovki(UstanovitOshibkuPriPodgotovkeUnita $command): void
    {
        if (empty($this->podgotovka)) {
            throw new \DomainException('unit.podgotovka_ne_nachalas');
        }

        if ($this->podgotovka->isFinish()) {
            throw new \DomainException('unit.resultat_podgotovki_uge_ustanovlen');
        }

        $state = $this->state->newState(new OshibkaPodgotovki());
        $this->recordThat(new OshibkaPodgotovkiUnitaUstanovlena($command->unitId, $command->textOtRunnera, $state->toArray($this)));
    }

    private function applyOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $this->podgotovka = $this->podgotovka->ustanovitOshibku($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehPodgotovki(string $textOtRunnera): void
    {
        if (empty($this->podgotovka)) {
            throw new \DomainException('unit.podgotovka_ne_nachalas');
        }

        if ($this->podgotovka->isFinish()) {
            throw new \DomainException('unit.resultat_podgotovki_uge_ustanovlen');
        }

        $state = $this->state->newState(new Podgotovlen());
        $this->recordThat(new UspehPodgotovkiUnitaUstanovlen($this->getId(), $textOtRunnera, $state->toArray($this)));
    }

    private function applyUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $this->podgotovka = $this->podgotovka->ustanovitUspeh($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Обновление
    public function nachatObnovlenieUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }

        if (empty($this->sborka)) {
            throw new \DomainException('unit.sborka_ne_nachalas');
        }

        $state = $this->state->newState(new VOcherediNaObnovlenie());
        $this->recordThat(new ObnovlenieUnitaNachalos($this->getId(), $jobId, $state->toArray($this)));
    }

    private function applyObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $this->obnovlenie = RunnerJob::start($fact->jobId);

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuObnovleniya(UstanovitOshibkuObnovleniyaUnita $command): void
    {
        if (empty($this->obnovlenie)) {
            throw new \DomainException('unit.obnovlenie_ne_nachalas');
        }

        if ($this->obnovlenie->isFinish()) {
            throw new \DomainException('unit.resultat_obnovleniya_uge_ustanovlen');
        }

        $state = $this->state->newState(new OshibkaObnovleniya());
        $this->recordThat(new OshibkaObnovleniyaUnitaUstanovlena($command->unitId, $command->textOtRunnera, $state->toArray($this)));
    }

    private function applyOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $this->obnovlenie = $this->obnovlenie->ustanovitOshibku($fact->textOtRunnera);

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehObnovleniya(string $textOtRunnera, array $configUnita): void
    {
        if (empty($this->obnovlenie)) {
            throw new \DomainException('unit.obnovlenie_ne_nachalas');
        }

        if ($this->obnovlenie->isFinish()) {
            throw new \DomainException('unit.resultat_obnovleniya_uge_ustanovlen');
        }

        if ($this->esliZapushen()) {
            $state = $this->state->newState(new Zapushen());
        } elseif ($this->esliPodgotovlen()) {
            $state = $this->state->newState(new Podgotovlen());
        } else {
            $state = $this->state->newState(new Sobran());
        }
        $this->recordThat(new UspehObnovleniyaUnitaUstanovlen($this->getId(), $textOtRunnera, $configUnita, $state->toArray($this)));
    }

    private function applyUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $this->obnovlenie = $this->obnovlenie->ustanovitUspeh($fact->textOtRunnera);

        if (!empty($fact->configUnita)) {
            $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
        }

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //----------Сброс подготовки
    public function nachatSbrosPodgotovkiUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }

        if ($this->zapusk && $this->zapusk->isSuccess()) {
            throw new \DomainException('unit.zapushen');
        }

        if (empty($this->podgotovka)) {
            throw new \DomainException('unit.podgotovka_ne_nachalas');
        }

        $errs = $this->configUnita->validateValues($this->variableValues);
        if (!empty($errs)) {
            throw new \DomainException(join(', ', $errs));
        }

        $state = $this->state->newState(new VOcherediNaSbrosPodgotovki());
        $this->recordThat(new SbrosPodgotovkiNachalsya($this->getId(), $jobId, $state->toArray($this)));
    }

    private function applySbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $this->sbrosPodgotovki = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuSbrosaPodgotovki(UstanovitOshibkuSbrosaPodgotovkiUnita $command): void
    {
        if (empty($this->sbrosPodgotovki)) {
            throw new \DomainException('unit.sbrosPodgotovki_ne_nachalas');
        }

        if ($this->sbrosPodgotovki->isFinish()) {
            throw new \DomainException('unit.resultat_sbrosPodgotovki_uge_ustanovlen');
        }

        $state = $this->state->newState(new OshibkaSbrosaPodgotovki());
        $this->recordThat(new OshibkaSbrosaPodgotovkiUnitaUstanovlena($command->unitId, $command->textOtRunnera, $state->toArray($this)));
    }

    private function applyOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $this->sbrosPodgotovki = $this->sbrosPodgotovki->ustanovitOshibku($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehSbrosaPodgotovki(string $textOtRunnera): void
    {
        if (empty($this->sbrosPodgotovki)) {
            throw new \DomainException('unit.sbrosPodgotovki_ne_nachalas');
        }

        if ($this->sbrosPodgotovki->isFinish()) {
            throw new \DomainException('unit.resultat_sbrosPodgotovki_uge_ustanovlen');
        }

        $state = $this->state->newState(new Sobran());
        $this->recordThat(new UspehSbrosaPodgotovkiUnitaUstanovlen($this->getId(), $textOtRunnera, $state->toArray($this)));
    }

    private function applyUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $this->sbrosPodgotovki = $this->sbrosPodgotovki->ustanovitUspeh($fact->textOtRunnera);
        $this->podgotovka = null;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Запуск
    public function nachatZapuskUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }

        if ($this->zapusk && $this->zapusk->isSuccess()) {
            throw new \DomainException('unit.zapushen');
        }

        if (empty($this->podgotovka)) {
            throw new \DomainException('unit.podgotovka_ne_nachalas');
        }

        $errs = $this->configUnita->validateValues($this->variableValues);
        if (!empty($errs)) {
            throw new \DomainException(join(', ', $errs));
        }

        $state = $this->state->newState(new VOcherediNaZapusk());
        $this->recordThat(new ZapuskUnitNachalsya($this->getId(), $jobId, $state->toArray($this)));
    }

    private function applyZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $this->zapusk = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuZapuska(UstanovitOshibkuZapuska $command): void
    {
        if (empty($this->zapusk)) {
            throw new \DomainException('unit.zapusk_ne_nachalas');
        }

        if ($this->zapusk->isFinish()) {
            throw new \DomainException('unit.resultat_zapusk_uge_ustanovlen');
        }

        $state = $this->state->newState(new OshibkaZapuska());
        $this->recordThat(new OshibkaZapuskaUnitaUstanovlena($command->unitId, $command->textOtRunnera, $state->toArray($this)));
    }

    private function applyOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $this->zapusk = $this->zapusk->ustanovitOshibku($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehZapuska(string $textOtRunnera): void
    {
        if (empty($this->zapusk)) {
            throw new \DomainException('unit.zapusk_ne_nachalas');
        }

        if ($this->zapusk->isFinish()) {
            throw new \DomainException('unit.resultat_zapusk_uge_ustanovlen');
        }

        $state = $this->state->newState(new Zapushen());
        $this->recordThat(new UspehZapuskaUnitaUstanovlen($this->getId(), $textOtRunnera, $state->toArray($this)));
    }

    private function applyUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $this->zapusk = $this->zapusk->ustanovitUspeh($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Остановка
    public function nachatOstanovkuUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }

        if (!$this->zapusk || !$this->zapusk->isSuccess()) {
            throw new \DomainException('unit.ne_zapushen');
        }

        $errs = $this->configUnita->validateValues($this->variableValues);
        if (!empty($errs)) {
            throw new \DomainException(join(', ', $errs));
        }

        $state = $this->state->newState(new VOcherediNaOstanovku());
        $this->recordThat(new OstanovkaUnitaNachalas($this->getId(), $jobId, $state->toArray($this)));
    }

    private function applyOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $this->ostanovka = RunnerJob::start($fact->jobId);

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuOstanovki(UstanovitOshibkuOstanovkiUnita $command): void
    {
        if (empty($this->ostanovka)) {
            throw new \DomainException('unit.ostanovka_ne_nachalas');
        }

        if ($this->ostanovka->isFinish()) {
            throw new \DomainException('unit.resultat_ostanovka_uge_ustanovlen');
        }

        $state = $this->state->newState(new OshibkaOstanovki());
        $this->recordThat(new OshibkaOstanovkiUnitaUstanovlena($command->unitId, $command->textOtRunnera, $state->toArray($this)));
    }

    private function applyOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $this->ostanovka = $this->ostanovka->ustanovitOshibku($fact->textOtRunnera);

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehOstanovki(string $textOtRunnera): void
    {
        if (empty($this->ostanovka)) {
            throw new \DomainException('unit.ostanovka_ne_nachalas');
        }

        if ($this->ostanovka->isFinish()) {
            throw new \DomainException('unit.resultat_ostanovka_uge_ustanovlen');
        }

        $state = $this->state->newState(new Podgotovlen());
        $this->recordThat(new UspehOstanovkiUnitaUstanovlen($this->getId(), $textOtRunnera, $state->toArray($this)));
    }

    private function applyUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $this->ostanovka = $this->ostanovka->ustanovitUspeh($fact->textOtRunnera);
        $this->zapusk = null;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Удаление
    public function nachatUdalenieUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }

        if ($this->zapusk && $this->zapusk->isSuccess()) {
            throw new \DomainException('unit.zapushen');
        }

        $state = $this->state->newState(new VOcherediNaUdalenie());
        $this->recordThat(new UdalenieUnitaNachalos($this->getId(), $jobId, $state->toArray($this)));
    }

    private function applyUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $this->udalenie = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuUdaleniya(UstanovitOshibkuUdaleniya $command): void
    {
        if (empty($this->udalenie)) {
            throw new \DomainException('unit.$this->udalenie_ne_nachalas');
        }

        if ($this->udalenie->isFinish()) {
            throw new \DomainException('unit.resultat_udaleniya_uge_ustanovlen');
        }

        $state = $this->state->newState(new Sloman());
        $this->recordThat(new OshibkaUdaleniyaUnitaUstanovlena($command->unitId, $command->textOtRunnera, $state->toArray($this)));
    }

    private function applyOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $this->udalenie = $this->udalenie->ustanovitOshibku($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehUdaleniya(string $textOtRunnera): void
    {
        if (empty($this->udalenie)) {
            throw new \DomainException('unit.$this->udalenie_ne_nachalas');
        }

        if ($this->udalenie->isFinish()) {
            throw new \DomainException('unit.resultat_udaleniya_uge_ustanovlen');
        }

        $state = $this->state->newState(new Udalen());
        $this->recordThat(new UspehUdaleniyaUnitaUstanovlen($this->getId(), $textOtRunnera, $state->toArray($this)));
    }

    private function applyUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $this->udalenie = $this->udalenie->ustanovitUspeh($fact->textOtRunnera);
        $this->isDeleted = true;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function udalitSlomaniyUnit(): void
    {
        if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }

        if ($this->isDeleted) {
            throw new \DomainException('unit.uge_udalen');
        }

        $state = $this->state->newState(new UdalenVruchnuyu());
        $this->recordThat(new SlomaniyUnitUdalen($this->getId(), $state->toArray($this)));
    }

    private function applySlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->isDeleted = true;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }
}
