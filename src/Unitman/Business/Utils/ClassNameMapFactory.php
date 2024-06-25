<?php

namespace App\Unitman\Business\Utils;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\Event\ProektPostavlenVOcheredNaUdalenie;
use App\Unitman\Business\Model\Project\Event\ProektPostavlenVOcheredNaSborku;
use App\Unitman\Business\Model\Project\Event\ProjectDataWasChanged;
use App\Unitman\Business\Model\Project\Event\ProjectWasAdded;
use App\Unitman\Business\Model\Project\Event\ProjectWasBuilt;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeleted;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeletedManually;
use App\Unitman\Business\Model\Project\Event\ProjectWasDisabled;
use App\Unitman\Business\Model\Project\Event\ProjectWasEnabled;
use App\Unitman\Business\Model\Project\Event\ProjectWasNotBuilt;
use App\Unitman\Business\Model\Project\Event\ProjectWasNotDeleted;
use App\Unitman\Business\Model\Project\Event\UserAddedToProject;
use App\Unitman\Business\Model\Project\Event\UserRemovedFromProject;
use App\Unitman\Business\Model\Project\ProjectId;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\Event\AccessToRepoConfirmed;
use App\Unitman\Business\Model\Repo\Event\CredentialsOfRepoWasChanged;
use App\Unitman\Business\Model\Repo\Event\RepoWasAdded;
use App\Unitman\Business\Model\Repo\Event\RepoWasDeleted;
use App\Unitman\Business\Model\Repo\RepoId;
use App\Unitman\Business\Model\Unit;
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
use App\Unitman\Business\Model\Unit\UnitId;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use EventSauce\EventSourcing\ExplicitlyMappedClassNameInflector;

final class ClassNameMapFactory
{
    function getMap(): ExplicitlyMappedClassNameInflector
    {
        $classToEventTypeMap = [
            Repo::class => UnitmanClassNameMapEnum::Repo,
            RepoId::class => UnitmanClassNameMapEnum::RepoId,
            AccessToRepoConfirmed::class => UnitmanClassNameMapEnum::AccessToRepoConfirmed,
            CredentialsOfRepoWasChanged::class => UnitmanClassNameMapEnum::CredentialsOfRepoWasChanged,
            RepoWasDeleted::class => UnitmanClassNameMapEnum::RepoWasDeleted,
            RepoWasAdded::class => UnitmanClassNameMapEnum::RepoWasAdded,

            Project::class => UnitmanClassNameMapEnum::Project,
            ProjectId::class => UnitmanClassNameMapEnum::ProjectId,
            ProjectWasAdded::class => UnitmanClassNameMapEnum::ProjectWasAdded,
            ProjectDataWasChanged::class => UnitmanClassNameMapEnum::ProjectDataWasChanged,
            ProektPostavlenVOcheredNaSborku::class => UnitmanClassNameMapEnum::ProektPostavlenVOcheredNaSborku,
            ProektPostavlenVOcheredNaUdalenie::class => UnitmanClassNameMapEnum::ProektPostavlenVOcheredNaUdalenie,
            ProjectWasBuilt::class => UnitmanClassNameMapEnum::ProjectWasBuilt,
            ProjectWasDeleted::class => UnitmanClassNameMapEnum::ProjectWasDeleted,
            ProjectWasDeletedManually::class => UnitmanClassNameMapEnum::ProjectWasDeletedManually,
            ProjectWasDisabled::class => UnitmanClassNameMapEnum::ProjectWasDisabled,
            ProjectWasEnabled::class => UnitmanClassNameMapEnum::ProjectWasEnabled,
            ProjectWasNotBuilt::class => UnitmanClassNameMapEnum::ProjectWasNotBuilt,
            ProjectWasNotDeleted::class => UnitmanClassNameMapEnum::ProjectWasNotDeleted,
            UserAddedToProject::class => UnitmanClassNameMapEnum::UserAddedToProject,
            UserRemovedFromProject::class => UnitmanClassNameMapEnum::UserRemovedFromProject,

            Unit::class => UnitmanClassNameMapEnum::Unit,
            UnitId::class => UnitmanClassNameMapEnum::UnitId,
            ObnovlenieUnitaNachalos::class => UnitmanClassNameMapEnum::ObnovlenieUnitaNachalos,
            OshibkaObnovleniyaUnitaUstanovlena::class => UnitmanClassNameMapEnum::OshibkaObnovleniyaUnitaUstanovlena,
            OshibkaOstanovkiUnitaUstanovlena::class => UnitmanClassNameMapEnum::OshibkaOstanovkiUnitaUstanovlena,
            OshibkaPodgotovkiUnitaUstanovlena::class => UnitmanClassNameMapEnum::OshibkaPodgotovkiUnitaUstanovlena,
            OshibkaSborkiUnitaUstanovlena::class => UnitmanClassNameMapEnum::OshibkaSborkiUnitaUstanovlena,
            OshibkaSbrosaPodgotovkiUnitaUstanovlena::class => UnitmanClassNameMapEnum::OshibkaSbrosaPodgotovkiUnitaUstanovlena,
            OshibkaUdaleniyaUnitaUstanovlena::class => UnitmanClassNameMapEnum::OshibkaUdaleniyaUnitaUstanovlena,
            OshibkaZapuskaUnitaUstanovlena::class => UnitmanClassNameMapEnum::OshibkaZapuskaUnitaUstanovlena,
            OstanovkaUnitaNachalas::class => UnitmanClassNameMapEnum::OstanovkaUnitaNachalas,
            PeremenieUnitaZapolneni::class => UnitmanClassNameMapEnum::PeremenieUnitaZapolneni,
            PodgotovkaUnitaNachalas::class => UnitmanClassNameMapEnum::PodgotovkaUnitaNachalas,
            SborkaUnitNachalas::class => UnitmanClassNameMapEnum::SborkaUnitNachalas,
            SbrosPodgotovkiNachalsya::class => UnitmanClassNameMapEnum::SbrosPodgotovkiNachalsya,
            SlomaniyUnitUdalen::class => UnitmanClassNameMapEnum::SlomaniyUnitUdalen,
            UdalenieUnitaNachalos::class => UnitmanClassNameMapEnum::UdalenieUnitaNachalos,
            UnitSozdan::class => UnitmanClassNameMapEnum::UnitSozdan,
            UspehObnovleniyaUnitaUstanovlen::class => UnitmanClassNameMapEnum::UspehObnovleniyaUnitaUstanovlen,
            UspehOstanovkiUnitaUstanovlen::class => UnitmanClassNameMapEnum::UspehOstanovkiUnitaUstanovlen,
            UspehPodgotovkiUnitaUstanovlen::class => UnitmanClassNameMapEnum::UspehPodgotovkiUnitaUstanovlen,
            UspehSborkiUnitaUstanovlen::class => UnitmanClassNameMapEnum::UspehSborkiUnitaUstanovlen,
            UspehSbrosaPodgotovkiUnitaUstanovlen::class => UnitmanClassNameMapEnum::UspehSbrosaPodgotovkiUnitaUstanovlen,
            UspehUdaleniyaUnitaUstanovlen::class => UnitmanClassNameMapEnum::UspehUdaleniyaUnitaUstanovlen,
            UspehZapuskaUnitaUstanovlen::class => UnitmanClassNameMapEnum::UspehZapuskaUnitaUstanovlen,
            ZapuskUnitNachalsya::class => UnitmanClassNameMapEnum::ZapuskUnitNachalsya,
            KonfigUnitaUstanovlen::class => UnitmanClassNameMapEnum::KonfigUnitaUstanovlen,
            Unit\Event\IzmenenieVetkiNachalos::class => UnitmanClassNameMapEnum::IzmenenieVetkiNachalos,
            Unit\Event\OshibkaIzmeneniyaVetkiUnitaUstanovlena::class => UnitmanClassNameMapEnum::OshibkaIzmeneniyaVetkiUnitaUstanovlena,
            Unit\Event\UspehIzmeneniyaVetkiUstanovlen::class => UnitmanClassNameMapEnum::UspehIzmeneniyaVetkiUstanovlen,
        ];
        $map = [];

        foreach ($classToEventTypeMap as $key => $enum) {
            $map[$key] = $enum->value;
        }

        return new ExplicitlyMappedClassNameInflector($map);
    }
}
