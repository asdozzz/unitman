<?php

namespace App\Unitman\Business\Utils;

enum UnitmanClassNameMapEnum: string
{
    case Repo = 'Repo';
    case RepoId = 'RepoId';
    case RepoWasAdded = 'RepoWasAdded';
    case AccessToRepoConfirmed = 'AccessToRepoConfirmed';
    case CredentialsOfRepoWasChanged = 'CredentialsOfRepoWasChanged';
    case RepoWasDeleted = 'RepoWasDeleted';

    case Project = 'Project';
    case ProjectId = 'ProjectId';
    case ProjectWasAdded = 'ProjectWasAdded';
    case ProjectDataWasChanged = 'ProjectDataWasChanged';
    case ProektPostavlenVOcheredNaSborku = 'ProektPostavlenVOcheredNaSborku';
    case ProektPostavlenVOcheredNaUdalenie = 'ProektPostavlenVOcheredNaUdalenie';

    case ProjectWasBuilt = 'ProjectWasBuilt';
    case ProjectWasDeleted = 'ProjectWasDeleted';
    case ProjectWasDeletedManually = 'ProjectWasDeletedManually';
    case ProjectWasDisabled = 'ProjectWasDisabled';
    case ProjectWasEnabled = 'ProjectWasEnabled';
    case ProjectWasNotBuilt = 'ProjectWasNotBuilt';
    case ProjectWasNotDeleted = 'ProjectWasNotDeleted';
    case UserAddedToProject = 'UserAddedToProject';
    case UserRemovedFromProject = 'UserRemovedFromProject';

    case Unit = 'Unit';
    case UnitId = 'UnitId';
    case ObnovlenieUnitaNachalos = 'ObnovlenieUnitaNachalos';
    case OshibkaObnovleniyaUnitaUstanovlena = 'OshibkaObnovleniyaUnitaUstanovlena';
    case OshibkaOstanovkiUnitaUstanovlena = 'OshibkaOstanovkiUnitaUstanovlena';
    case OshibkaPodgotovkiUnitaUstanovlena = 'OshibkaPodgotovkiUnitaUstanovlena';
    case OshibkaSborkiUnitaUstanovlena = 'OshibkaSborkiUnitaUstanovlena';
    case OshibkaSbrosaPodgotovkiUnitaUstanovlena = 'OshibkaSbrosaPodgotovkiUnitaUstanovlena';
    case OshibkaUdaleniyaUnitaUstanovlena = 'OshibkaUdaleniyaUnitaUstanovlena';
    case OshibkaZapuskaUnitaUstanovlena = 'OshibkaZapuskaUnitaUstanovlena';
    case OstanovkaUnitaNachalas = 'OstanovkaUnitaNachalas';
    case PeremenieUnitaZapolneni = 'PeremenieUnitaZapolneni';
    case PodgotovkaUnitaNachalas = 'PodgotovkaUnitaNachalas';
    case SborkaUnitNachalas = 'SborkaUnitNachalas';
    case SbrosPodgotovkiNachalsya = 'SbrosPodgotovkiNachalsya';
    case SlomaniyUnitUdalen = 'SlomaniyUnitUdalen';
    case UdalenieUnitaNachalos = 'UdalenieUnitaNachalos';
    case UnitSozdan = 'UnitSozdan';
    case UspehObnovleniyaUnitaUstanovlen = 'UspehObnovleniyaUnitaUstanovlen';
    case UspehOstanovkiUnitaUstanovlen = 'UspehOstanovkiUnitaUstanovlen';
    case UspehPodgotovkiUnitaUstanovlen = 'UspehPodgotovkiUnitaUstanovlen';
    case UspehSborkiUnitaUstanovlen = 'UspehSborkiUnitaUstanovlen';
    case UspehSbrosaPodgotovkiUnitaUstanovlen = 'UspehSbrosaPodgotovkiUnitaUstanovlen';
    case UspehUdaleniyaUnitaUstanovlen = 'UspehUdaleniyaUnitaUstanovlen';
    case UspehZapuskaUnitaUstanovlen = 'UspehZapuskaUnitaUstanovlen';
    case ZapuskUnitNachalsya = 'ZapuskUnitNachalsya';
    case KonfigUnitaUstanovlen = 'KonfigUnitaUstanovlen';

    case IzmenenieVetkiNachalos = 'IzmenenieVetkiNachalos';
    case OshibkaIzmeneniyaVetkiUnitaUstanovlena = 'OshibkaIzmeneniyaVetkiUnitaUstanovlena';
    case UspehIzmeneniyaVetkiUstanovlen = 'UspehIzmeneniyaVetkiUstanovlen';
    case ObnovlenieKodaUnitaPosleZapuskaNachalos = 'ObnovlenieKodaUnitaPosleZapuskaNachalos';
    case OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena = 'OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena';
    case UdalenieUnitaPosleZapuskaNachalos = 'UdalenieUnitaPosleZapuskaNachalos';
    case OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena = 'OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena';

}
