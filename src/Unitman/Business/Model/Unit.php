<?php

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Command\Unit\IzmenitVetkuUnita;
use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuObnovleniyaUnitaPosleZapuska;
use App\Unitman\Business\Model\Project\ProjectUser;
use App\Unitman\Business\Model\Project\ProjectUserRole;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Unit\ConfigUnita;
use App\Unitman\Business\Model\Unit\ConfigUnita\KonfigServisa;
use App\Unitman\Business\Model\Unit\Event\AvtosborkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\IzmenenieVetkiNachalos;
use App\Unitman\Business\Model\Unit\Event\KodVetkiIzmenilsyaVHranilishe;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieKodaUnitaPosleZapuskaNachalos;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\OshibkaAvtosborkiUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaIzmeneniyaVetkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaObnovleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaOstanovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSborkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSbrosaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaUdaleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaZapuskaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OstanovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\PeremenieUnitaZapolneni;
use App\Unitman\Business\Model\Unit\Event\PodgotovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\SborkaUnitNachalas;
use App\Unitman\Business\Model\Unit\Event\SbrosPodgotovkiNachalsya;
use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\StatistikaPoKonteineruObnovlena;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaPosleZapuskaNachalos;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UnitSozdanSystemoi;
use App\Unitman\Business\Model\Unit\Event\UspehIzmeneniyaVetkiUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehObnovleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehOstanovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSborkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSbrosaPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehZapuskaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ZapuskUnitNachalsya;
use App\Unitman\Business\Model\Unit\Runner\RunnerJob;
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
use App\Unitman\Business\Model\Unit\State\VOcheredNaIzmenenieVetki;
use App\Unitman\Business\Model\Unit\State\Zapushen;
use App\Unitman\Business\Model\Unit\StatistikaKonteinera;
use App\Unitman\Business\Model\Unit\UnitBranch;
use App\Unitman\Business\Model\Unit\UnitId;
use App\Unitman\Business\Model\Unit\UnitName;
use App\Unitman\Business\Model\Unit\UnitProject;
use App\Unitman\Business\Model\Unit\VariableValue;
use DomainException;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use Exception;

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

    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?AbstractState $state;
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

    private ?RunnerJob $izmenenieVetki = null;

    private ?string $obnovleniePosleZapuska = null;

    private ?string $udaleniePosleZapuska = null;

    private ?string $avtosborkaUnitaSystemoi = null;

    private bool $isDeleted = false;

    private ?UnitBranch $newBranch = null;

    private ?int $unixtimePoslednegoObnovleniyaKodaUnita = null;

    private ?int $unixtimePoslednegoObnovleniyaKodaVHranilishe = null;

    private bool $unitSozdanSystemoi = false;

    private ?StatistikaKonteinera $statistikaKonteinera = null;

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

    function esliSobran(): bool
    {
        return !empty($this->sborka) && $this->sborka->isSuccess();
    }

    function esliPoluchenResultatSborki(): bool
    {
        return !empty($this->sborka) && $this->sborka->isFinish();
    }

    function esliPoluchenResultatZapuska(): bool
    {
        return !empty($this->zapusk) && $this->zapusk->isFinish();
    }

    function esliPoluchenResultatOstanovki(): bool
    {
        return !empty($this->ostanovka) && $this->ostanovka->isFinish();
    }

    function esliPoluchenResultatIzmeneniyaVetki(): bool
    {
        return !empty($this->izmenenieVetki) && $this->izmenenieVetki->isFinish();
    }
    function esliPoluchenResultatUdaleniya(): bool
    {
        return !empty($this->udalenie) && $this->udalenie->isFinish();
    }

    function esliPoluchenResultatObnovleniya(): bool
    {
        return !empty($this->obnovlenie) && $this->obnovlenie->isFinish();
    }

    function esliPoluchenResultatPodgotovki(): bool
    {
        return !empty($this->podgotovka) && $this->podgotovka->isFinish();
    }

    function esliPoluchenResultatSbrosaPodgotovki(): bool
    {
        return !empty($this->sbrosPodgotovki) && $this->sbrosPodgotovki->isFinish();
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
            'izmenenieVetki',
        ];

        foreach ($props as $prop) {
            if (!empty($this->{$prop}) && !$this->{$prop}->isFinish()) {
                return true;
            }
        }

        return false;
    }

    public function esliRazreshenoUpravlyatUnitom(ProjectUser $projectUser): bool
    {
        return $this->authorId === $projectUser->userId || $projectUser->userRole === ProjectUserRole::ADMIN || $this->unitSozdanSystemoi;
    }

    public static function sozdatUnit(string $id, string $authorId, SozdatUnit $command): self
    {
        if (empty($command->projectId)) {
            throw new DomainException('unit.projectId_is_empty');
        }
        if (empty($authorId)) {
            throw new DomainException('unit.authorId_is_empty');
        }
        $unit = new self(UnitId::fromString($id));
        $state = new Sozdan();
        $unit->recordThat(new UnitSozdan($id, $authorId, $command->projectId, $command->unitName, $command->branch, $state->toArray($unit)));
        return $unit;
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

    public static function sozdatUnitSystemoi(string $id, Account $account, SozdatUnit $command): self
    {
        if (empty($command->projectId)) {
            throw new DomainException('unit.projectId_is_empty');
        }
        if (!$account->isSystemRole) {
            throw new DomainException('unit.author_must_have_system_role');
        }
        $unit = new self(UnitId::fromString($id));
        $state = new Sozdan();
        $unit->recordThat(new UnitSozdanSystemoi($id, $account->id, $command->projectId, $command->unitName, $command->branch, $state->toArray($unit)));
        return $unit;
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUnitSozdanSystemoi(UnitSozdanSystemoi $fact): void
    {
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->project = new UnitProject($fact->projectId);
        $this->name = new UnitName($fact->name);
        $this->branch = new UnitBranch($fact->branch);
        $this->authorId = $fact->authorId;
        $this->unitSozdanSystemoi = true;
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
     * @param string $newBranch
     * @return void
     */
    public function validateNewBranch(ProjectUser $projectUser, string $newBranch): void
    {
        $this->proverkaPrav($projectUser);

        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (!$this->state || $this->state->getCode() !== Sobran::CODE) {
            throw new DomainException('unit.allow_after_build');
        }

        if (!$this->branch) {
            throw new DomainException('unit.old_branch_is_empty');
        }

        $this->branch->validateNewBranch($newBranch);
    }


    private function newState(AbstractState $state): AbstractState
    {
        if (empty($this->state)) {
            throw new DomainException('unit.state_not_found');
        }
        return $this->state->newState($state);
    }

    public function nachatSborkuUnita(JobId $jobId, int $unixtime): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if (!empty($this->sborka) && $this->sborka->isSuccess()) {
            throw new DomainException('unit.uge_sobran');
        }
        $state = $this->newState(new VOcherediNaSborku());
        $this->recordThat(new SborkaUnitNachalas($this->getId(), (string) $jobId, $state->toArray($this), $unixtime));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applySborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $this->sborka = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->unixtimePoslednegoObnovleniyaKodaUnita = $fact->unixtime ?? time();
        $this->unixtimePoslednegoObnovleniyaKodaVHranilishe = $fact->unixtime ?? time();
    }

    public function ustanovitOshibkuSborki(array $steps): void
    {
        if (empty($this->sborka)) {
            throw new DomainException('unit.sborka_ne_nachalas');
        }

        if ($this->sborka->isFinish()) {
            throw new DomainException('unit.resultat_sborki_uge_ustanovlen');
        }
        $state = $this->newState(new OshibkaSborki());
        $this->recordThat(new OshibkaSborkiUnitaUstanovlena($this->getId(), $steps, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $this->sborka = $this->sborka->ustanovitOshibku($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->avtosborkaUnitaSystemoi = null;
    }

    public function ustanovitUspehSborki(array $steps, array $configUnita): void
    {
        //TODO вынести создание объекта конфига наружу
        if (empty($this->sborka)) {
            throw new DomainException('unit.sborka_ne_nachalas');
        }

        if ($this->sborka->isFinish()) {
            throw new DomainException('unit.resultat_sborki_uge_ustanovlen');
        }

        $state = $this->newState(new Sobran());
        $this->recordThat(new UspehSborkiUnitaUstanovlen($this->getId(), $steps, $state->toArray($this)));

        $errs = $this->validateConfig($configUnita);

        if (empty($errs)) {
            $this->recordThat(new KonfigUnitaUstanovlen($this->getId(), $configUnita));
        }

    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $this->sborka = $this->sborka->ustanovitUspeh($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function applyKonfigUnitaUstanovlen(KonfigUnitaUstanovlen $fact): void
    {
        $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
    }

    public function validateConfig(array $configUnita): array
    {
        $errors = [];
        try {
            if (empty($configUnita)) {
                throw new DomainException('unit.config_is_empty');
            }
            ConfigUnita::fromArray($configUnita);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    public function nachatIzmenenieVetkiUnita(JobId $jobId, ProjectUser $projectUser, string $newBranch): void
    {
        $this->validateNewBranch($projectUser, $newBranch);

        $state = $this->newState(new VOcheredNaIzmenenieVetki());
        $this->recordThat(new IzmenenieVetkiNachalos($this->getId(), (string)$jobId, $newBranch, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyIzmenenieVetkiNachalos(IzmenenieVetkiNachalos $fact): void
    {
        $this->izmenenieVetki = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->newBranch = new UnitBranch($fact->newBranch);
        $this->unixtimePoslednegoObnovleniyaKodaUnita = null;
        $this->unixtimePoslednegoObnovleniyaKodaVHranilishe = null;
    }

    public function ustanovitOshibkuIzmeneniyaVetki(array $steps): void
    {
        if (empty($this->izmenenieVetki)) {
            throw new DomainException('unit.izmenenieVetki_ne_nachalas');
        }

        if ($this->izmenenieVetki->isFinish()) {
            throw new DomainException('unit.resultat_izmenenieVetki_uge_ustanovlen');
        }
        $state = $this->newState(new Sobran());
        $this->recordThat(new OshibkaIzmeneniyaVetkiUnitaUstanovlena($this->getId(), $steps, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaIzmeneniyaVetkiUnitaUstanovlena(OshibkaIzmeneniyaVetkiUnitaUstanovlena $fact): void
    {
        $this->izmenenieVetki = $this->izmenenieVetki->ustanovitOshibku($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->newBranch = null;
    }

    public function ustanovitUspehIzmeneniyaVetki(array $steps): void
    {
        if (empty($this->izmenenieVetki)) {
            throw new DomainException('unit.izmenenieVetki_ne_nachalas');
        }

        if ($this->izmenenieVetki->isFinish()) {
            throw new DomainException('unit.resultat_izmenenieVetki_uge_ustanovlen');
        }

        $state = $this->newState(new Sobran());
        $this->recordThat(new UspehIzmeneniyaVetkiUstanovlen($this->getId(), $steps, $state->toArray($this), (string)$this->newBranch));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehIzmeneniyaVetkiUstanovlen(UspehIzmeneniyaVetkiUstanovlen $fact): void
    {
        $this->izmenenieVetki = $this->izmenenieVetki->ustanovitUspeh($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->branch = new UnitBranch($fact->newBranch);
    }

    public function zapolnitPeremenie(array $values): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (empty($this->configUnita)) {
            throw new DomainException('unit.config_unita_ne_opredelen');
        }

        $tmpVariables = $this->makeVariableCollectionByArray($values);

        $errs = $this->configUnita->validateValues($tmpVariables);
        if (!empty($errs)) {
            throw new DomainException(join(', ', $errs));
        }

        $this->variableValues = $tmpVariables;

        if (empty($this->state)) {
            $state = new Sozdan();
        } else {
            $state = $this->state;
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

    public function esliConfigZapolnenPravilon(): bool
    {
        if (empty($this->configUnita)) {
            return false;
        }

        $errs = $this->configUnita->validateValues($this->variableValues);

        if (!empty($errs)) {
            return false;
        }

        return true;
    }

    public function nachatPodgotovkuUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (!empty($this->podgotovka) && $this->podgotovka->isSuccess()) {
            throw new DomainException('unit.podgotovka_uge_bila');
        }

        if (empty($this->sborka)) {
            throw new DomainException('unit.sborka_ne_nachalas');
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

    public function ustanovitOshibkuPodgotovki(array $steps): void
    {
        if (empty($this->podgotovka)) {
            throw new DomainException('unit.podgotovka_ne_nachalas');
        }

        if ($this->podgotovka->isFinish()) {
            throw new DomainException('unit.resultat_podgotovki_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaPodgotovki());
        $this->recordThat(new OshibkaPodgotovkiUnitaUstanovlena($this->getId(), $steps, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $this->podgotovka = $this->podgotovka->ustanovitOshibku($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->obnovleniePosleZapuska = null;
        $this->avtosborkaUnitaSystemoi = null;
    }

    public function ustanovitUspehPodgotovki(array $steps): void
    {
        if (empty($this->podgotovka)) {
            throw new DomainException('unit.podgotovka_ne_nachalas');
        }

        if ($this->podgotovka->isFinish()) {
            throw new DomainException('unit.resultat_podgotovki_uge_ustanovlen');
        }

        $state = $this->newState(new Podgotovlen());
        $this->recordThat(new UspehPodgotovkiUnitaUstanovlen($this->getId(), $steps, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $this->podgotovka = $this->podgotovka->ustanovitUspeh($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //-----Стата

    public function onbovitStatistikuKonteinera(ObnovitStatistikuPoKontaineruUnita $command): void
    {
        $this->recordThat(
            new StatistikaPoKonteineruObnovlena(
                id: $this->getId(),
                cpuPercent: $command->cpuPercent,
                memoryPercent: $command->memoryPercent,
                memoryUsage: $command->memoryUsage,
                netIO: $command->netIO
            )
        );
    }

    private function applyStatistikaPoKonteineruObnovlena(StatistikaPoKonteineruObnovlena $fact): void
    {
        $this->statistikaKonteinera = new StatistikaKonteinera(
            cpuPercent: $fact->cpuPercent,
            memoryPercent: $fact->memoryPercent,
            memoryUsage: $fact->memoryUsage,
            netIO: $fact->netIO
        );
    }

    //---------Обновление
    public function nachatObnovlenieUnita(JobId $jobId, int $unixtime): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (empty($this->sborka)) {
            throw new DomainException('unit.sborka_ne_nachalas');
        }

        $state = $this->newState(new VOcherediNaObnovlenie());
        $this->recordThat(new ObnovlenieUnitaNachalos($this->getId(), (string) $jobId, $state->toArray($this), $unixtime));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $this->obnovlenie = RunnerJob::start($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->unixtimePoslednegoObnovleniyaKodaUnita = $fact->unixtime ?? time();
        $this->unixtimePoslednegoObnovleniyaKodaVHranilishe = $fact->unixtime ?? time();
    }

    public function nachatObnovlenieUnitaPosleZapuska(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if (!empty($this->obnovleniePosleZapuska)) {
            throw new DomainException('unit.obnovlenie_uge_zapusheno');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (!$this->esliSobran()) {
            throw new DomainException('unit.unit_ne_sobran');
        }

        $this->recordThat(new ObnovlenieKodaUnitaPosleZapuskaNachalos($this->getId(), (string) $jobId));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyObnovlenieKodaUnitaPosleZapuskaNachalos(ObnovlenieKodaUnitaPosleZapuskaNachalos $fact): void
    {
        $this->obnovleniePosleZapuska = $fact->jobId;
    }


    public function ustanovitOshibkuObnovleniyaPosleZapuska(string $error): void
    {
        $this->recordThat(new OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena($this->getId(), $error));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaObnovleniyaUnitaPosleZapuskaUstanovlena(OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena $fact): void
    {
        $this->obnovleniePosleZapuska = null;
    }

    public function nachatAvtosborku(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if (!empty($this->avtosborkaUnitaSystemoi)) {
            throw new DomainException('unit.avtosborka_uge_zapusheno');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        $this->recordThat(new AvtosborkaUnitaNachalas($this->getId(), (string) $jobId));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyAvtosborkaUnitaNachalas(AvtosborkaUnitaNachalas $fact): void
    {
        $this->avtosborkaUnitaSystemoi = $fact->jobId;
    }

    public function ustanovitOshibkuAvtosborki(string $error): void
    {
        $this->recordThat(new OshibkaAvtosborkiUstanovlena($this->getId(), $error));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaAvtosborkiUstanovlena(OshibkaAvtosborkiUstanovlena $fact): void
    {
        $this->avtosborkaUnitaSystemoi = null;
    }


    public function nachatUdalenieUnitaPosleZapuska(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if (!empty($this->udaleniePosleZapuska)) {
            throw new DomainException('unit.udalenie_uge_zapusheno');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (!$this->esliZapushen()) {
            throw new DomainException('unit.unit_ne_zapushen');
        }

        $this->recordThat(new UdalenieUnitaPosleZapuskaNachalos($this->getId(), (string) $jobId));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUdalenieUnitaPosleZapuskaNachalos(UdalenieUnitaPosleZapuskaNachalos $fact): void
    {
        $this->udaleniePosleZapuska = $fact->jobId;
    }


    public function ustanovitOshibkaUdaleniyaUnitaPosleZapuska(string $error): void
    {
        $this->recordThat(new OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena($this->getId(), $error));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaUdaleniyaUnitaPosleZapuskaUstanovlena(OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena $fact): void
    {
        $this->udaleniePosleZapuska = null;
    }

    public function ustanovitOshibkuObnovleniya(array $steps): void
    {
        if (empty($this->obnovlenie)) {
            throw new DomainException('unit.obnovlenie_ne_nachalas');
        }

        if ($this->obnovlenie->isFinish()) {
            throw new DomainException('unit.resultat_obnovleniya_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaObnovleniya());
        $this->recordThat(new OshibkaObnovleniyaUnitaUstanovlena($this->getId(), $steps, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $this->obnovlenie = $this->obnovlenie->ustanovitOshibku($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->obnovleniePosleZapuska = null;
    }

    public function ustanovitUspehObnovleniya(array $steps, array $configUnita): void
    {
        //TODO вынести создание объекта конфига наружу
        if (empty($this->obnovlenie)) {
            throw new DomainException('unit.obnovlenie_ne_nachalas');
        }

        if ($this->obnovlenie->isFinish()) {
            throw new DomainException('unit.resultat_obnovleniya_uge_ustanovlen');
        }

        if ($this->esliZapushen()) {
            $state = $this->newState(new Zapushen());
        } elseif ($this->esliPodgotovlen()) {
            $state = $this->newState(new Podgotovlen());
        } else {
            $state = $this->newState(new Sobran());
        }

        $this->recordThat(new UspehObnovleniyaUnitaUstanovlen($this->getId(), $steps, $state->toArray($this)));

        $errs = $this->validateConfig($configUnita);

        if (empty($errs)) {
            $this->recordThat(new KonfigUnitaUstanovlen($this->getId(), $configUnita));
        }

    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $this->obnovlenie = $this->obnovlenie->ustanovitUspeh($fact->steps);

        if (!empty($fact->configUnita)) {
            $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
        }

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //----------Сброс подготовки
    public function nachatSbrosPodgotovkiUnita(JobId $jobId): void
    {
        $errors = [];

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

        if (!empty($errors)) {
            throw new Exception($errors[0]);
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

    public function ustanovitOshibkuSbrosaPodgotovki(array $steps): void
    {
        if (empty($this->sbrosPodgotovki)) {
            throw new DomainException('unit.sbrosPodgotovki_ne_nachalas');
        }

        if ($this->sbrosPodgotovki->isFinish()) {
            throw new DomainException('unit.resultat_sbrosPodgotovki_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaSbrosaPodgotovki());
        $this->recordThat(new OshibkaSbrosaPodgotovkiUnitaUstanovlena($this->getId(), $steps, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $this->sbrosPodgotovki = $this->sbrosPodgotovki->ustanovitOshibku($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->obnovleniePosleZapuska = null;
    }

    public function ustanovitUspehSbrosaPodgotovki(array $steps): void
    {
        if (empty($this->sbrosPodgotovki)) {
            throw new DomainException('unit.sbrosPodgotovki_ne_nachalas');
        }

        if ($this->sbrosPodgotovki->isFinish()) {
            throw new DomainException('unit.resultat_sbrosPodgotovki_uge_ustanovlen');
        }

        $state = $this->newState(new Sobran());
        $this->recordThat(new UspehSbrosaPodgotovkiUnitaUstanovlen($this->getId(), $steps, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $this->sbrosPodgotovki = $this->sbrosPodgotovki->ustanovitUspeh($fact->steps);
        $this->podgotovka = null;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Запуск
    public function nachatZapuskUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if ($this->zapusk && $this->zapusk->isSuccess()) {
            throw new DomainException('unit.zapushen');
        }

        if (empty($this->podgotovka)) {
            throw new DomainException('unit.podgotovka_ne_nachalas');
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
        $this->obnovleniePosleZapuska = null;
        $this->avtosborkaUnitaSystemoi = null;
    }

    public function ustanovitOshibkuZapuska(array $steps): void
    {
        if (empty($this->zapusk)) {
            throw new DomainException('unit.zapusk_ne_nachalas');
        }

        if ($this->zapusk->isFinish()) {
            throw new DomainException('unit.resultat_zapusk_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaZapuska());
        $this->recordThat(new OshibkaZapuskaUnitaUstanovlena($this->getId(), $steps, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $this->zapusk = $this->zapusk->ustanovitOshibku($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehZapuska(array $steps): void
    {
        if (empty($this->zapusk)) {
            throw new DomainException('unit.zapusk_ne_nachalas');
        }

        if ($this->zapusk->isFinish()) {
            throw new DomainException('unit.resultat_zapusk_uge_ustanovlen');
        }

        $state = $this->newState(new Zapushen());
        $this->recordThat(new UspehZapuskaUnitaUstanovlen($this->getId(), $steps, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $this->zapusk = $this->zapusk->ustanovitUspeh($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Остановка
    public function nachatOstanovkuUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (!$this->zapusk || !$this->zapusk->isSuccess()) {
            throw new DomainException('unit.ne_zapushen');
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

    public function ustanovitOshibkuOstanovki(array $steps): void
    {
        if (empty($this->ostanovka)) {
            throw new DomainException('unit.ostanovka_ne_nachalas');
        }

        if ($this->ostanovka->isFinish()) {
            throw new DomainException('unit.resultat_ostanovka_uge_ustanovlen');
        }

        $state = $this->newState(new OshibkaOstanovki());

        $this->recordThat(new OshibkaOstanovkiUnitaUstanovlena($this->getId(), $steps, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $this->ostanovka = $this->ostanovka->ustanovitOshibku($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->obnovleniePosleZapuska = null;
    }

    public function ustanovitUspehOstanovki(array $steps): void
    {
        if (empty($this->ostanovka)) {
            throw new DomainException('unit.ostanovka_ne_nachalas');
        }

        if ($this->ostanovka->isFinish()) {
            throw new DomainException('unit.resultat_ostanovka_uge_ustanovlen');
        }

        $state = $this->newState(new Podgotovlen());
        $this->recordThat(new UspehOstanovkiUnitaUstanovlen($this->getId(), $steps, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $this->ostanovka = $this->ostanovka->ustanovitUspeh($fact->steps);
        $this->zapusk = null;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Удаление
    public function nachatUdalenieUnita(JobId $jobId): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
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
        $this->udaleniePosleZapuska = null;
    }

    public function ustanovitOshibkuUdaleniya(array $steps): void
    {
        if (empty($this->udalenie)) {
            throw new DomainException('unit.udalenie_ne_nachalas');
        }

        if ($this->udalenie->isFinish()) {
            throw new DomainException('unit.resultat_udaleniya_uge_ustanovlen');
        }

        $state = $this->newState(new Sloman());
        $this->recordThat(new OshibkaUdaleniyaUnitaUstanovlena($this->getId(), $steps, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $this->udalenie = $this->udalenie->ustanovitOshibku($fact->steps);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->udaleniePosleZapuska = null;
    }

    public function ustanovitUspehUdaleniya(array $steps): void
    {
        if (empty($this->udalenie)) {
            throw new DomainException('unit.udalenie_ne_nachalas');
        }

        if ($this->udalenie->isFinish()) {
            throw new DomainException('unit.resultat_udaleniya_uge_ustanovlen');
        }

        $state = $this->newState(new Udalen());
        $this->recordThat(new UspehUdaleniyaUnitaUstanovlen($this->getId(), $steps, $state->toArray($this)));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $this->udalenie = $this->udalenie->ustanovitUspeh($fact->steps);
        $this->isDeleted = true;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->udaleniePosleZapuska = null;
    }

    public function udalitSlomaniyUnit(): void
    {
        /*if ($this->isWaitResultFromRunner()) {
            throw new \DomainException('unit.wait_runner');
        }*/

        if ($this->isDeleted) {
            throw new DomainException('unit.uge_udalen');
        }

        $state = $this->newState(new UdalenVruchnuyu());
        $this->recordThat(new SlomaniyUnitUdalen($this->getId(), $state->toArray($this)));
    }

    private function applySlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->isDeleted = true;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function izmenitVremyaPoslednegoIzmeneniyaKodaVHranilishe(int $unixtime): void
    {
        if ($this->unixtimePoslednegoObnovleniyaKodaVHranilishe > $unixtime) {
            return;
        }
        $this->recordThat(new KodVetkiIzmenilsyaVHranilishe($this->getId(), $unixtime));
    }

    private function applyKodVetkiIzmenilsyaVHranilishe(KodVetkiIzmenilsyaVHranilishe $fact): void
    {
        $this->unixtimePoslednegoObnovleniyaKodaVHranilishe = $fact->unixtime;
    }

    public function getName(): string
    {
        if (empty($this->name)) {
            throw new DomainException('unit.name_not_found');
        }

        return (string) $this->name;
    }

    public function getBranch(): string
    {
        if (empty($this->branch)) {
            throw new DomainException('unit.branch_not_found');
        }

        return (string) $this->branch;
    }

    public function getProjectId(): string
    {
        if (empty($this->project)) {
            throw new DomainException('unit.project_not_found');
        }

        return $this->project->id;
    }

    public function poluchitWorkflowIdDlySborki(): string
    {
        if (empty($this->sborka)) {
            throw new DomainException('unit.sborka_not_found');
        }
        return $this->sborka->getJobId();
    }

    public function poluchitWorkflowIdDlyObnovleniya(): string
    {
        if (empty($this->obnovlenie)) {
            throw new DomainException('unit.obnovlenie_not_found');
        }
        return $this->obnovlenie->getJobId();
    }

    public function poluchitWorkflowIdDlyPodgotovki(): string
    {
        if (empty($this->podgotovka)) {
            throw new DomainException('unit.podgotovka_not_found');
        }
        return $this->podgotovka->getJobId();
    }

    public function poluchitWorkflowIdDlySbrosaPodgotovki(): string
    {
        if (empty($this->sbrosPodgotovki)) {
            throw new DomainException('unit.sbrosPodgotovki_not_found');
        }
        return $this->sbrosPodgotovki->getJobId();
    }

    public function poluchitWorkflowIdDlyZapuska(): string
    {
        if (empty($this->zapusk)) {
            throw new DomainException('unit.zapusk_not_found');
        }
        return $this->zapusk->getJobId();
    }

    public function poluchitWorkflowIdDlyOstanovki(): string
    {
        if (empty($this->ostanovka)) {
            throw new DomainException('unit.ostanovka_not_found');
        }
        return $this->ostanovka->getJobId();
    }

    public function poluchitWorkflowIdDlyUdaleniya(): string
    {
        if (empty($this->udalenie)) {
            throw new DomainException('unit.udalenie_not_found');
        }
        return $this->udalenie->getJobId();
    }

    public function poluchitWorkflowIdDlyIzmeneniya(): string
    {
        if (empty($this->izmenenieVetki)) {
            throw new DomainException('unit.izmenenieVetki_not_found');
        }
        return $this->izmenenieVetki->getJobId();
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
     * @return KonfigServisa[]
     * */
    public function poluchitKonfigServisov(): array
    {
        return $this->configUnita?->getServices() ?? [];
    }

    /**
     * @return void
     */
    private function validateConfigValues(): void
    {
        if (empty($this->configUnita)) {
            throw new DomainException('unit.config_unita_ne_opredelen');
        }

        $errs = $this->configUnita->validateValues($this->variableValues);

        if (!empty($errs)) {
            throw new DomainException(join(', ', $errs));
        }
    }


    function esliMognoObnovit(): bool
    {
        if ($this->isDeleted) return false;
        if ($this->isWaitResultFromRunner()) return false;
        if (empty($this->sborka)) return false;
        if (!empty($this->podgotovka)) return false;

        return true;
    }

    function getConfig(): ConfigUnita|null
    {
        return $this->configUnita;
    }

    function proverkaPrav(ProjectUser $projectUser): void
    {
        if (!$this->esliRazreshenoUpravlyatUnitom($projectUser)) {
            throw new DomainException('unit.ne_hvataet_prav');
        }
    }

    function poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska(): ?string
    {
        return $this->obnovleniePosleZapuska;
    }

    function poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska(): ?string
    {
        return $this->udaleniePosleZapuska;
    }

    function poluchitWorkflowIdDlySozdaniyaUnitaSystemoi(): ?string
    {
        return $this->avtosborkaUnitaSystemoi;
    }

    /**
     * @psalm-ignore-nullable-return
     * */
    function getAuthorId(): ?string
    {
        return $this->authorId;
    }

    function esliNugnoObnovitKodUnita(): bool
    {
        return $this->unixtimePoslednegoObnovleniyaKodaVHranilishe > $this->unixtimePoslednegoObnovleniyaKodaUnita;
    }
}
