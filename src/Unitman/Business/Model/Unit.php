<?php

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Command\Unit\SozdatUnit;
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

/**
 * @template-implements AggregateRoot<UnitId>
 * */
final class Unit implements AggregateRoot
{
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?UnitName $name;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?UnitBranch $branch;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?UnitProject $project;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?string $authorId;

    private ?ConfigUnita $configUnita = null;

    private ?string $url = null;

    /**
     * @var array<VariableValue>
     * */
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
    public static function sozdatUnit(string $id, string $authorId, SozdatUnit $command): static
    {
        if (empty($command->projectId)) {
            throw new \DomainException('unit.projectId_is_empty');
        }
        if (empty($authorId)) {
            throw new \DomainException('unit.authorId_is_empty');
        }
        $unit = new static(UnitId::fromString($id));
        $state = new Sozdan();
        $unit->recordThat(new UnitSozdan($id, $authorId, $command->projectId, $command->unitName, $command->branch, $state->toArray($unit)));
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

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUnitSozdan(UnitSozdan $fact): void
    {
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->project = new UnitProject($fact->projectId);
        $this->name = new UnitName($fact->name);
        $this->branch = new UnitBranch($fact->branch);
        $this->authorId = $fact->authorId;
    }

    private function newState(AbstractState $state): AbstractState
    {
        if (empty($this->state)) {
            throw new \DomainException('unit.state_not_found');
        }
        return $this->state->newState($state);
    }

    public function nachatSborkuUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('unit.udalen');
        }

        if (!empty($this->sborka) && $this->sborka->isSuccess()) {
            throw new \DomainException('unit.uge_sobran');
        }
        $state = $this->newState(new VOcherediNaSborku());
        $this->recordThat(new SborkaUnitNachalas($this->getId(), (string) $jobId, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applySborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $this->sborka = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuSborki(string $textOtRunnera): void
    {
        if (empty($this->sborka)) {
            throw new \DomainException('unit.sborka_ne_nachalas');
        }

        if ($this->sborka->isFinish()) {
            throw new \DomainException('unit.resultat_sborki_uge_ustanovlen');
        }
        $state = $this->newState(new OshibkaSborki());
        $this->recordThat(new OshibkaSborkiUnitaUstanovlena($this->getId(), $textOtRunnera, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $this->sborka = $this->sborka->ustanovitOshibku($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehSborki(string $textOtRunnera, array $configUnita): void
    {
        //TODO вынести создание объекта конфига наружу
        if (empty($this->sborka)) {
            throw new \DomainException('unit.sborka_ne_nachalas');
        }

        if ($this->sborka->isFinish()) {
            throw new \DomainException('unit.resultat_sborki_uge_ustanovlen');
        }

        $errs = $this->validateConfig($configUnita);

        if (!empty($errs)) {
            $configUnita = null;
        }

        $state = $this->newState(new Sobran());
        $this->recordThat(new UspehSborkiUnitaUstanovlen($this->getId(), $textOtRunnera, $configUnita, $state->toArray($this)));
    }

    public function validateConfig(array $configUnita): array
    {
        if (empty($configUnita)) return [];
        $errors = [];
        try {
            ConfigUnita::fromArray($configUnita);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $this->sborka = $this->sborka->ustanovitUspeh($fact->textOtRunnera);
        if (!empty($fact->configUnita)) {
            $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
        }
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function zapolnitPeremenie(array $values): void
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
            $state = $this->newState(new Zapushen());
        } elseif ($this->esliPodgotovlen()) {
            $state = $this->newState(new Podgotovlen());
        } else {
            $state = $this->newState(new Sobran());
        }

        $this->recordThat(new PeremenieUnitaZapolneni($this->getId(), $values, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
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

        $this->validateConfigValues();
        $state = $this->newState(new VOcherediNaPodgotovku());
        $this->recordThat(new PodgotovkaUnitaNachalas($this->getId(), (string) $jobId, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyPodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $this->podgotovka = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuPodgotovki(string $textOtRunnera): void
    {
        if (empty($this->podgotovka)) {
            throw new \DomainException('unit.podgotovka_ne_nachalas');
        }

        if ($this->podgotovka->isFinish()) {
            throw new \DomainException('unit.resultat_podgotovki_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaPodgotovki());
        $this->recordThat(new OshibkaPodgotovkiUnitaUstanovlena($this->getId(), $textOtRunnera, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
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

        $state = $this->newState(new Podgotovlen());
        $this->recordThat(new UspehPodgotovkiUnitaUstanovlen($this->getId(), $textOtRunnera, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
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

        $state = $this->newState(new VOcherediNaObnovlenie());
        $this->recordThat(new ObnovlenieUnitaNachalos($this->getId(), (string) $jobId, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $this->obnovlenie = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuObnovleniya(string $textOtRunnera): void
    {
        if (empty($this->obnovlenie)) {
            throw new \DomainException('unit.obnovlenie_ne_nachalas');
        }

        if ($this->obnovlenie->isFinish()) {
            throw new \DomainException('unit.resultat_obnovleniya_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaObnovleniya());
        $this->recordThat(new OshibkaObnovleniyaUnitaUstanovlena($this->getId(), $textOtRunnera, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $this->obnovlenie = $this->obnovlenie->ustanovitOshibku($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehObnovleniya(string $textOtRunnera, array $configUnita): void
    {
        //TODO вынести создание объекта конфига наружу
        if (empty($this->obnovlenie)) {
            throw new \DomainException('unit.obnovlenie_ne_nachalas');
        }

        if ($this->obnovlenie->isFinish()) {
            throw new \DomainException('unit.resultat_obnovleniya_uge_ustanovlen');
        }

        $errs = $this->validateConfig($configUnita);

        //TODO придумать как убрать эту какаху
        if (!empty($errs)) {
            $configUnita = null;
            $textOtRunnera = 'Invalid config';
            $state = $this->newState(new OshibkaObnovleniya());
        } else {
            if ($this->esliZapushen()) {
                $state = $this->newState(new Zapushen());
            } elseif ($this->esliPodgotovlen()) {
                $state = $this->newState(new Podgotovlen());
            } else {
                $state = $this->newState(new Sobran());
            }
        }

        $this->recordThat(new UspehObnovleniyaUnitaUstanovlen($this->getId(), $textOtRunnera, $configUnita, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $this->obnovlenie = $this->obnovlenie->ustanovitUspeh($fact->textOtRunnera);

        if (!empty($fact->configUnita)) {
            $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
        }

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //----------Сброс подготовки
    public function nachatSbrosPodgotovkiUnita(JobId $jobId, string $userId): void
    {
        $errors = $this->esliMognoSbrositPodgotvku($userId);

        if (!empty($errors)) {
            throw new \Exception($errors[0]);
        }

        $this->validateConfigValues();

        $state = $this->newState(new VOcherediNaSbrosPodgotovki());
        $this->recordThat(new SbrosPodgotovkiNachalsya($this->getId(), (string) $jobId, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applySbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $this->sbrosPodgotovki = RunnerJob::start($fact->jobId);

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuSbrosaPodgotovki(string $textOtRunnera): void
    {
        if (empty($this->sbrosPodgotovki)) {
            throw new \DomainException('unit.sbrosPodgotovki_ne_nachalas');
        }

        if ($this->sbrosPodgotovki->isFinish()) {
            throw new \DomainException('unit.resultat_sbrosPodgotovki_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaSbrosaPodgotovki());
        $this->recordThat(new OshibkaSbrosaPodgotovkiUnitaUstanovlena($this->getId(), $textOtRunnera, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
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

        $state = $this->newState(new Sobran());
        $this->recordThat(new UspehSbrosaPodgotovkiUnitaUstanovlen($this->getId(), $textOtRunnera, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
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

        $this->validateConfigValues();

        $state = $this->newState(new VOcherediNaZapusk());
        $this->recordThat(new ZapuskUnitNachalsya($this->getId(), (string) $jobId, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $this->zapusk = RunnerJob::start($fact->jobId);

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuZapuska(string $textOtRunnera): void
    {
        if (empty($this->zapusk)) {
            throw new \DomainException('unit.zapusk_ne_nachalas');
        }

        if ($this->zapusk->isFinish()) {
            throw new \DomainException('unit.resultat_zapusk_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaZapuska());
        $this->recordThat(new OshibkaZapuskaUnitaUstanovlena($this->getId(), $textOtRunnera, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $this->zapusk = $this->zapusk->ustanovitOshibku($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehZapuska(string $textOtRunnera, string $projectProxyHost, string $projectName): void
    {
        if (empty($this->zapusk)) {
            throw new \DomainException('unit.zapusk_ne_nachalas');
        }

        if ($this->zapusk->isFinish()) {
            throw new \DomainException('unit.resultat_zapusk_uge_ustanovlen');
        }

        $pathinfo = parse_url($projectProxyHost);
        /** @var array|false $pathinfo*/

        if ($pathinfo === false) {
            throw new \DomainException('unit.invalid_proxy_host');
        }

        $unitUrl = $pathinfo['scheme'].'://'.$this->name.'.'.$projectName.'.'.$pathinfo['host'];
        if (!empty($pathinfo['port'])) {
            $unitUrl .= ':'.$pathinfo['port'];
        }
        $state = $this->newState(new Zapushen());
        $this->recordThat(new UspehZapuskaUnitaUstanovlen($this->getId(), $textOtRunnera, $unitUrl, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $this->url = $fact->url;
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


        $this->validateConfigValues();

        $state = $this->newState(new VOcherediNaOstanovku());
        $this->recordThat(new OstanovkaUnitaNachalas($this->getId(),(string) $jobId, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $this->ostanovka = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuOstanovki(string $textOtRunnera): void
    {
        if (empty($this->ostanovka)) {
            throw new \DomainException('unit.ostanovka_ne_nachalas');
        }

        if ($this->ostanovka->isFinish()) {
            throw new \DomainException('unit.resultat_ostanovka_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaOstanovki());

        $this->recordThat(new OshibkaOstanovkiUnitaUstanovlena($this->getId(), $textOtRunnera, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
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

        $state = $this->newState(new Podgotovlen());
        $this->recordThat(new UspehOstanovkiUnitaUstanovlen($this->getId(), $textOtRunnera, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
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

        $state = $this->newState(new VOcherediNaUdalenie());
        $this->recordThat(new UdalenieUnitaNachalos($this->getId(), (string) $jobId, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $this->udalenie = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuUdaleniya(string $textOtRunnera): void
    {
        if (empty($this->udalenie)) {
            throw new \DomainException('unit.udalenie_ne_nachalas');
        }

        if ($this->udalenie->isFinish()) {
            throw new \DomainException('unit.resultat_udaleniya_uge_ustanovlen');
        }

        $state = $this->newState(new Sloman());
        $this->recordThat(new OshibkaUdaleniyaUnitaUstanovlena($this->getId(), $textOtRunnera, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $this->udalenie = $this->udalenie->ustanovitOshibku($fact->textOtRunnera);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehUdaleniya(string $textOtRunnera): void
    {
        if (empty($this->udalenie)) {
            throw new \DomainException('unit.udalenie_ne_nachalas');
        }

        if ($this->udalenie->isFinish()) {
            throw new \DomainException('unit.resultat_udaleniya_uge_ustanovlen');
        }

        $state = $this->newState(new Udalen());
        $this->recordThat(new UspehUdaleniyaUnitaUstanovlen($this->getId(), $textOtRunnera, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $this->udalenie = $this->udalenie->ustanovitUspeh($fact->textOtRunnera);
        $this->isDeleted = true;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function udalitSlomaniyUnit(): void
    {
        /*if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }*/

        if ($this->isDeleted) {
            throw new \DomainException('unit.uge_udalen');
        }

        $state = $this->newState(new UdalenVruchnuyu());
        $this->recordThat(new SlomaniyUnitUdalen($this->getId(), $state->toArray($this)));
    }

    private function applySlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->isDeleted = true;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function getName(): string
    {
        if (empty($this->name)) {
            throw new \DomainException('unit.name_not_found');
        }

        return (string) $this->name;
    }

    public function getBranch(): string
    {
        if (empty($this->branch)) {
            throw new \DomainException('unit.branch_not_found');
        }

        return (string) $this->branch;
    }

    public function getProjectId(): string
    {
        if (empty($this->project)) {
            throw new \DomainException('unit.project_not_found');
        }

        return $this->project->id;
    }

    public function poluchitWorkflowIdDlySborki(): string
    {
        if (empty($this->sborka)) {
            throw new \DomainException('unit.sborka_not_found');
        }
        return $this->sborka->getJobId();
    }

    public function poluchitWorkflowIdDlyObnovleniya(): string
    {
        if (empty($this->obnovlenie)) {
            throw new \DomainException('unit.obnovlenie_not_found');
        }
        return $this->obnovlenie->getJobId();
    }

    public function poluchitWorkflowIdDlyPodgotovki(): string
    {
        if (empty($this->podgotovka)) {
            throw new \DomainException('unit.podgotovka_not_found');
        }
        return $this->podgotovka->getJobId();
    }

    public function poluchitWorkflowIdDlySbrosaPodgotovki(): string
    {
        if (empty($this->sbrosPodgotovki)) {
            throw new \DomainException('unit.sbrosPodgotovki_not_found');
        }
        return $this->sbrosPodgotovki->getJobId();
    }

    public function poluchitWorkflowIdDlyZapuska(): string
    {
        if (empty($this->zapusk)) {
            throw new \DomainException('unit.zapusk_not_found');
        }
        return $this->zapusk->getJobId();
    }

    public function poluchitWorkflowIdDlyOstanovki(): string
    {
        if (empty($this->ostanovka)) {
            throw new \DomainException('unit.ostanovka_not_found');
        }
        return $this->ostanovka->getJobId();
    }

    public function poluchitWorkflowIdDlyUdaleniya(): string
    {
        if (empty($this->udalenie)) {
            throw new \DomainException('unit.udalenie_not_found');
        }
        return $this->udalenie->getJobId();
    }

    public function poluchitKomandiPodgotovki(): array
    {
        return $this->configUnita?->getPrepare() ?? [];
    }

    public function poluchitKomandiSbrosaPodgotovki(): array
    {
        return $this->configUnita?->getResetPrepare() ?? [];
    }

    public function poluchitKomandiZapuska(): array
    {
        return $this->configUnita?->getUp() ?? [];
    }

    public function poluchitKomandiOstanovki(): array
    {
        return $this->configUnita?->getDown() ?? [];
    }

    /**
     * @return array<VariableValue>
     * */
    public function poluchitZnacheniyaPeremenih(): array
    {
        return $this->variableValues;
    }

    /**
     * @return void
     */
    private function validateConfigValues(): void
    {
        if (empty($this->configUnita)) {
            throw new \DomainException('unit.config_unita_ne_opredelen');
        }

        $errs = $this->configUnita->validateValues($this->variableValues);

        if (!empty($errs)) {
            throw new \DomainException(join(', ', $errs));
        }
    }

    /**
     * @return array
     */
    function esliMognoSbrositPodgotvku(string $userId): array
    {
        $errors = [];
        if (!$this->esliRazreshenoUpravlyatUnitom($userId)) {
            $errors[] = 'unit.ne_hvataet_prav';
        }

        if ($this->isDeleted) {
            $errors[] = 'unit.udalen';
        }

        if ($this->isWaitResultFromRunner()) {
            $errors[] = 'unit.wait_runner';
        }

        if ($this->zapusk && $this->zapusk->isSuccess()) {
            $errors[] = 'unit.zapushen';
        }

        if (empty($this->podgotovka)) {
            $errors[] = 'unit.podgotovka_ne_nachalas';
        }
        return $errors;
    }

    function getConfig(): ConfigUnita|null
    {
        return $this->configUnita;
    }
}
