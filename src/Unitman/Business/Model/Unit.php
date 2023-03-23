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
use App\Unitman\Business\Model\Unit\VariableValue;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

final class Unit implements AggregateRoot
{
    private ?UnitName $name;
    private ?UnitBranch $branch;
    private string $projectId;

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

    function esliPodgotovlen(): bool
    {
        return !empty($this->podgotovka);
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

    public static function sozdatUnit(string $id, SozdatUnit $command): static
    {
        if (empty($command->projectId)) {
            throw new \DomainException('unit.projectId_is_empty');
        }
        $unit = new static(UnitId::fromString($id));
        $unit->recordThat(new UnitSozdan($id, $command->projectId, $command->unitName, $command->branch));
        return $unit;
    }

    private function applyUnitSozdan(UnitSozdan $fact): void
    {
        $this->state = new Sozdan();
        $this->projectId = $fact->projectId;
        $this->name = new UnitName($fact->name);
        $this->branch = new UnitBranch($fact->branch);
    }

    public function nachatSborkuUnita(string $jobId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if (!empty($this->sborka)) {
            throw new \DomainException('unit.uge_sobran');
        }

        $this->recordThat(new SborkaUnitNachalas($this->getId(), $jobId));
    }

    private function applySborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $this->sborka = RunnerJob::start($fact->jobId);
        $this->state = $this->state->newState(new VOcherediNaSborku());
    }

    public function ustanovitOshibkuSborki(UstanovitOshibkuSborkiUnita $command): void
    {
        if (empty($this->sborka)) {
            throw new \DomainException('unit.sborka_ne_nachalas');
        }

        if ($this->sborka->isFinish()) {
            throw new \DomainException('unit.resultat_sborki_uge_ustanovlen');
        }

        $this->recordThat(new OshibkaSborkiUnitaUstanovlena($command->unitId, $command->textOtRunnera));
    }

    private function applyOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $this->sborka = $this->sborka->ustanovitOshibku($fact->textOtRunnera);
        $this->state = $this->state->newState(new OshibkaSborki());
    }

    public function ustanovitUspehSborki(string $textOtRunnera, array $configUnita): void
    {
        if (empty($this->sborka)) {
            throw new \DomainException('unit.sborka_ne_nachalas');
        }

        if ($this->sborka->isFinish()) {
            throw new \DomainException('unit.resultat_sborki_uge_ustanovlen');
        }

        $this->recordThat(new UspehSborkiUnitaUstanovlen($this->getId(), $textOtRunnera, $configUnita));
    }

    private function applyUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $this->sborka = $this->sborka->ustanovitUspeh($fact->textOtRunnera);
        if (!empty($fact->configUnita)) {
            $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
        }
        $this->state = $this->state->newState(new Sobran());
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

        $errs = $this->configUnita->validateValues($this->variableValues);
        if (!empty($errs)) {
            throw new \DomainException(join(', ', $errs));
        }

        $this->recordThat(new PeremenieUnitaZapolneni($this->getId(), $values));
    }

    private function applyPeremenieUnitaZapolneni(PeremenieUnitaZapolneni $fact): void
    {
        $this->variableValues = array_map(fn(array $item) => new VariableValue($item['id']??'', $item['value']??''), $fact->values);

        if ($this->esliPodgotovlen()) {
            $this->state = $this->state->newState(new Podgotovlen());
        } else {
            $this->state = $this->state->newState(new Sobran());
        }
    }

    public function nachatPodgotovkuUnita(string $jobId): void
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

        $this->recordThat(new PodgotovkaUnitaNachalas($this->getId(), $jobId));
    }

    private function applyPodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $this->podgotovka = RunnerJob::start($fact->jobId);
        $this->state = $this->state->newState(new VOcherediNaPodgotovku());
    }

    public function ustanovitOshibkuPodgotovki(UstanovitOshibkuPriPodgotovkeUnita $command): void
    {
        if (empty($this->podgotovka)) {
            throw new \DomainException('unit.podgotovka_ne_nachalas');
        }

        if ($this->podgotovka->isFinish()) {
            throw new \DomainException('unit.resultat_podgotovki_uge_ustanovlen');
        }

        $this->recordThat(new OshibkaPodgotovkiUnitaUstanovlena($command->unitId, $command->textOtRunnera));
    }

    private function applyOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $this->podgotovka = $this->podgotovka->ustanovitOshibku($fact->textOtRunnera);
        $this->state = $this->state->newState(new OshibkaPodgotovki());
    }

    public function ustanovitUspehPodgotovki(string $textOtRunnera): void
    {
        if (empty($this->podgotovka)) {
            throw new \DomainException('unit.podgotovka_ne_nachalas');
        }

        if ($this->podgotovka->isFinish()) {
            throw new \DomainException('unit.resultat_podgotovki_uge_ustanovlen');
        }

        $this->recordThat(new UspehPodgotovkiUnitaUstanovlen($this->getId(), $textOtRunnera));
    }

    private function applyUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $this->podgotovka = $this->podgotovka->ustanovitUspeh($fact->textOtRunnera);
        $this->state = $this->state->newState(new Podgotovlen());
    }

    //---------Обновление
    public function nachatObnovlenieUnita(string $jobId): void
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

        if ($this->zapusk && $this->zapusk->isSuccess()) {
            throw new \DomainException('unit.zapushen');
        }

        $this->recordThat(new ObnovlenieUnitaNachalos($this->getId(), $jobId));
    }

    private function applyObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $this->obnovlenie = RunnerJob::start($fact->jobId);
        $this->state = $this->state->newState(new VOcherediNaObnovlenie());
    }

    public function ustanovitOshibkuObnovleniya(UstanovitOshibkuObnovleniyaUnita $command): void
    {
        if (empty($this->obnovlenie)) {
            throw new \DomainException('unit.obnovlenie_ne_nachalas');
        }

        if ($this->obnovlenie->isFinish()) {
            throw new \DomainException('unit.resultat_obnovleniya_uge_ustanovlen');
        }

        $this->recordThat(new OshibkaObnovleniyaUnitaUstanovlena($command->unitId, $command->textOtRunnera));
    }

    private function applyOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $this->obnovlenie = $this->obnovlenie->ustanovitOshibku($fact->textOtRunnera);
        $this->state = $this->state->newState(new OshibkaObnovleniya());
    }

    public function ustanovitUspehObnovleniya(string $textOtRunnera, array $configUnita): void
    {
        if (empty($this->obnovlenie)) {
            throw new \DomainException('unit.obnovlenie_ne_nachalas');
        }

        if ($this->obnovlenie->isFinish()) {
            throw new \DomainException('unit.resultat_obnovleniya_uge_ustanovlen');
        }

        $this->recordThat(new UspehObnovleniyaUnitaUstanovlen($this->getId(), $textOtRunnera, $configUnita));
    }

    private function applyUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $this->obnovlenie = $this->obnovlenie->ustanovitUspeh($fact->textOtRunnera);
        if (!empty($fact->configUnita)) {
            $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
        }
        if ($this->esliPodgotovlen()) {
            $this->state = $this->state->newState(new Podgotovlen());
        } else {
            $this->state = $this->state->newState(new Sobran());
        }
    }

    //----------Сброс подготовки
    public function nachatSbrosPodgotovkiUnita(string $jobId): void
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

        $this->recordThat(new SbrosPodgotovkiNachalsya($this->getId(), $jobId));
    }

    private function applySbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $this->sbrosPodgotovki = RunnerJob::start($fact->jobId);
        $this->state = $this->state->newState(new VOcherediNaSbrosPodgotovki());
    }

    public function ustanovitOshibkuSbrosaPodgotovki(UstanovitOshibkuSbrosaPodgotovkiUnita $command): void
    {
        if (empty($this->sbrosPodgotovki)) {
            throw new \DomainException('unit.sbrosPodgotovki_ne_nachalas');
        }

        if ($this->sbrosPodgotovki->isFinish()) {
            throw new \DomainException('unit.resultat_sbrosPodgotovki_uge_ustanovlen');
        }

        $this->recordThat(new OshibkaSbrosaPodgotovkiUnitaUstanovlena($command->unitId, $command->textOtRunnera));
    }

    private function applyOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $this->sbrosPodgotovki = $this->sbrosPodgotovki->ustanovitOshibku($fact->textOtRunnera);
        $this->state = $this->state->newState(new OshibkaSbrosaPodgotovki());
    }

    public function ustanovitUspehSbrosaPodgotovki(string $textOtRunnera): void
    {
        if (empty($this->sbrosPodgotovki)) {
            throw new \DomainException('unit.sbrosPodgotovki_ne_nachalas');
        }

        if ($this->sbrosPodgotovki->isFinish()) {
            throw new \DomainException('unit.resultat_sbrosPodgotovki_uge_ustanovlen');
        }

        $this->recordThat(new UspehSbrosaPodgotovkiUnitaUstanovlen($this->getId(), $textOtRunnera));
    }

    private function applyUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $this->sbrosPodgotovki = $this->sbrosPodgotovki->ustanovitUspeh($fact->textOtRunnera);
        $this->podgotovka = null;
        $this->state = $this->state->newState(new Sobran());
    }

    //---------Запуск
    public function nachatZapuskUnita(string $jobId): void
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

        $this->recordThat(new ZapuskUnitNachalsya($this->getId(), $jobId));
    }

    private function applyZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $this->zapusk = RunnerJob::start($fact->jobId);
        $this->state = $this->state->newState(new VOcherediNaZapusk());
    }

    public function ustanovitOshibkuZapuska(UstanovitOshibkuZapuska $command): void
    {
        if (empty($this->zapusk)) {
            throw new \DomainException('unit.zapusk_ne_nachalas');
        }

        if ($this->zapusk->isFinish()) {
            throw new \DomainException('unit.resultat_zapusk_uge_ustanovlen');
        }

        $this->recordThat(new OshibkaZapuskaUnitaUstanovlena($command->unitId, $command->textOtRunnera));
    }

    private function applyOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $this->zapusk = $this->zapusk->ustanovitOshibku($fact->textOtRunnera);
        $this->state = $this->state->newState(new OshibkaZapuska());
    }

    public function ustanovitUspehZapuska(string $textOtRunnera): void
    {
        if (empty($this->zapusk)) {
            throw new \DomainException('unit.zapusk_ne_nachalas');
        }

        if ($this->zapusk->isFinish()) {
            throw new \DomainException('unit.resultat_zapusk_uge_ustanovlen');
        }

        $this->recordThat(new UspehZapuskaUnitaUstanovlen($this->getId(), $textOtRunnera));
    }

    private function applyUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $this->zapusk = $this->zapusk->ustanovitUspeh($fact->textOtRunnera);
        $this->state = $this->state->newState(new Zapushen());
    }

    //---------Остановка
    public function nachatOstanovkuUnita(string $jobId): void
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

        $this->recordThat(new OstanovkaUnitaNachalas($this->getId(), $jobId));
    }

    private function applyOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $this->ostanovka = RunnerJob::start($fact->jobId);
        $this->state = $this->state->newState(new VOcherediNaOstanovku());
    }

    public function ustanovitOshibkuOstanovki(UstanovitOshibkuOstanovkiUnita $command): void
    {
        if (empty($this->ostanovka)) {
            throw new \DomainException('unit.ostanovka_ne_nachalas');
        }

        if ($this->ostanovka->isFinish()) {
            throw new \DomainException('unit.resultat_ostanovka_uge_ustanovlen');
        }

        $this->recordThat(new OshibkaOstanovkiUnitaUstanovlena($command->unitId, $command->textOtRunnera));
    }

    private function applyOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $this->ostanovka = $this->ostanovka->ustanovitOshibku($fact->textOtRunnera);
        $this->state = $this->state->newState(new OshibkaOstanovki());
    }

    public function ustanovitUspehOstanovki(string $textOtRunnera): void
    {
        if (empty($this->ostanovka)) {
            throw new \DomainException('unit.ostanovka_ne_nachalas');
        }

        if ($this->ostanovka->isFinish()) {
            throw new \DomainException('unit.resultat_ostanovka_uge_ustanovlen');
        }

        $this->recordThat(new UspehOstanovkiUnitaUstanovlen($this->getId(), $textOtRunnera));
    }

    private function applyUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $this->ostanovka = $this->ostanovka->ustanovitUspeh($fact->textOtRunnera);
        $this->zapusk = null;
        $this->state = $this->state->newState(new Podgotovlen());
    }

    //---------Удаление
    public function nachatUdalenieUnita(string $jobId): void
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

        $this->recordThat(new UdalenieUnitaNachalos($this->getId(), $jobId));
    }

    private function applyUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $this->udalenie = RunnerJob::start($fact->jobId);
        $this->state = $this->state->newState(new VOcherediNaUdalenie());
    }

    public function ustanovitOshibkuUdaleniya(UstanovitOshibkuUdaleniya $command): void
    {
        if (empty($this->udalenie)) {
            throw new \DomainException('unit.$this->udalenie_ne_nachalas');
        }

        if ($this->udalenie->isFinish()) {
            throw new \DomainException('unit.resultat_udaleniya_uge_ustanovlen');
        }

        $this->recordThat(new OshibkaUdaleniyaUnitaUstanovlena($command->unitId, $command->textOtRunnera));
    }

    private function applyOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $this->udalenie = $this->udalenie->ustanovitOshibku($fact->textOtRunnera);
        $this->state = $this->state->newState(new Sloman());
    }

    public function ustanovitUspehUdaleniya(string $textOtRunnera): void
    {
        if (empty($this->udalenie)) {
            throw new \DomainException('unit.$this->udalenie_ne_nachalas');
        }

        if ($this->udalenie->isFinish()) {
            throw new \DomainException('unit.resultat_udaleniya_uge_ustanovlen');
        }

        $this->recordThat(new UspehUdaleniyaUnitaUstanovlen($this->getId(), $textOtRunnera));
    }

    private function applyUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $this->udalenie = $this->udalenie->ustanovitUspeh($fact->textOtRunnera);
        $this->isDeleted = true;
        $this->state = $this->state->newState(new Udalen());
    }

    public function udalitSlomaniyUnit(): void
    {
        if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }

        if ($this->isDeleted) {
            throw new \DomainException('unit.uge_udalen');
        }
        $this->recordThat(new SlomaniyUnitUdalen($this->getId()));
    }

    private function applySlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->isDeleted = true;
        $this->state = $this->state->newState(new UdalenVruchnuyu());
    }

    public function getStateAsArray(): array
    {
        return $this->state->toArray($this);
    }
}
