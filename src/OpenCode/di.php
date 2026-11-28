<?php

declare(strict_types=1);

namespace App\OpenCode;

use App\OpenCode\Acl\AccountAdapter;
use App\OpenCode\Acl\MediatekaFaylyWorkspaceAdapter;
use App\OpenCode\Acl\RunnerAdapter;
use App\OpenCode\Acl\SkillAdapter;
use App\OpenCode\Acl\UnitmanAdapter;
use App\OpenCode\Business\AgentMode\Port\AgentModeRepository;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\WorkspaceRunnerRepository;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\Port\UnitSessionTurnRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\Port\WorkspaceNotificationsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetOpredelyatKanalUvedomleniyOpenCode;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetPoluchatTokenRedaktirovaniyaUnita;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Port\UmeetPoluchatNavyki;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Workspace\Port\WorkspaceMirror;
use App\OpenCode\Infra\Adapter\RamseyUuidGenerator;
use App\OpenCode\Infra\Repository\SqlAgentModeRepository;
use App\OpenCode\Infra\Repository\SqlHodOpenCodeRepository;
use App\OpenCode\Infra\Repository\SqlSessiyaOpenCodeRepository;
use App\OpenCode\Infra\Repository\SqlUchastnikiSessiiRepository;
use App\OpenCode\Infra\Repository\SqlWorkspaceRunnerRepository;
use App\OpenCode\Infra\Repository\SqlUvedomleniyaOpenCodeRepository;
use App\OpenCode\Infra\Repository\UnitSession\SqlUnitSessionRepository;
use App\OpenCode\Infra\Repository\UnitSession\SqlUnitSessionTurnRepository;
use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceMirror;
use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceNotificationsRepository;
use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceParticipantsRepository;
use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceSessionRepository;
use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceTurnRepository;
use App\OpenCode\Infra\Service\CentrifugoUvedomleniyaOpenCode;
use App\OpenCode\Infra\Service\KanalUvedomleniyOpenCode;
use App\OpenCode\Business\Utils\ImyaFajlaHoda;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configuration) {
    $services = $configuration->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$centrifugoSecret', '%env(CENTRIFUGO_SECRET)%')
        ->bind('$workspaceMaxFiles', '%env(int:MEDIATEKA_WORKSPACE_MAX_FILES)%')
        ->bind('$artifactMaxBytes', '%env(int:OPENCODE_ARTIFACT_MAX_BYTES)%')
        ->bind('$artifactMaxFiles', '%env(int:OPENCODE_ARTIFACT_MAX_FILES)%')
        ->bind('$artifactMaxTotalBytes', '%env(int:OPENCODE_ARTIFACT_MAX_TOTAL_BYTES)%')
        ->bind('$artifactPreviewMaxBytes', '%env(int:OPENCODE_ARTIFACT_PREVIEW_MAX_BYTES)%')
        ->bind('$artifactAllowedExtensions', '%env(OPENCODE_ARTIFACT_ALLOWED_EXT)%');

    $services->load('App\\OpenCode\\', './{Business,Infra,Acl,Api}')
        ->exclude([
            './{di.php,di_test.php,routing.php,Migrations,Tests}',
            './Business/Command',
            './Business/Model',
            './Business/ReadModel',
            './Business/AgentMode/Model',
            './Business/AgentMode/ReadModel',
            './Business/UnitSession/Model',
            './Business/UnitSession/ReadModel',
            './Business/Workspace/Model',
            './Business/Workspace/ReadModel',
        ])
        ->public();

    // Порты бизнес-слоя -> инфраструктурные реализации модуля.
    $services->set(SessiyaOpenCodeRepository::class, SqlSessiyaOpenCodeRepository::class);
    $services->set(UchastnikiSessiiRepository::class, SqlUchastnikiSessiiRepository::class);
    $services->set(HodOpenCodeRepository::class, SqlHodOpenCodeRepository::class);
    $services->set(AgentModeRepository::class, SqlAgentModeRepository::class);
    $services->set(WorkspaceRunnerRepository::class, SqlWorkspaceRunnerRepository::class);

    // Независимые агрегаты: у workspace и unit-сессии свои репозитории.
    $services->set(UnitSessionRepository::class, SqlUnitSessionRepository::class);
    $services->set(UnitSessionTurnRepository::class, SqlUnitSessionTurnRepository::class);
    $services->set(WorkspaceSessionRepository::class, SqlWorkspaceSessionRepository::class);
    $services->set(WorkspaceTurnRepository::class, SqlWorkspaceTurnRepository::class);
    $services->set(WorkspaceParticipantsRepository::class, SqlWorkspaceParticipantsRepository::class);
    $services->set(WorkspaceNotificationsRepository::class, SqlWorkspaceNotificationsRepository::class);
    $services->set(UuidGenerator::class, RamseyUuidGenerator::class);

    // Флаги cutover. Пока выключены — источник истины легаси. Валидация
    // комбинации выполняется в конструкторе: WRITE требует READ, а READ без
    // WRITE допустим только при включённом зеркале.
    $services->set(WorkspaceFeatureFlags::class)
        ->arg('$read', '%env(bool:OPENCODE_WORKSPACE_READ)%')
        ->arg('$write', '%env(bool:OPENCODE_WORKSPACE_WRITE)%')
        ->arg('$mirror', '%env(bool:OPENCODE_WORKSPACE_MIRROR_WRITE)%');

    // Одностороннее зеркало легаси -> новый агрегат. По умолчанию выключено:
    // включение — фаза 2 (`OPENCODE_WORKSPACE_MIRROR_WRITE=1`).
    $services->set(WorkspaceMirror::class, SqlWorkspaceMirror::class)
        ->arg('$enabled', '%env(bool:OPENCODE_WORKSPACE_MIRROR_WRITE)%');

    // Уведомления о новых сообщениях OpenCode.
    $services->set(UvedomleniyaOpenCodeRepository::class, SqlUvedomleniyaOpenCodeRepository::class);
    $services->set(UmeetPeredavatUvedomleniyaOpenCode::class, CentrifugoUvedomleniyaOpenCode::class);
    $services->set(UmeetOpredelyatKanalUvedomleniyOpenCode::class, KanalUvedomleniyOpenCode::class);

    // Порты бизнес-слоя -> Acl-адаптеры чужих модулей.
    $services->set(UmeetProveryatRekvizityOpenCode::class, AccountAdapter::class);
    $services->set(UmeetVypolnyatHodOpenCode::class, RunnerAdapter::class);
    $services->set(UmeetUpravlyatWorkspace::class, RunnerAdapter::class);
    $services->set(UmeetUpravlyatUnitami::class, UnitmanAdapter::class);
    $services->set(UmeetBronirovatUniti::class, UnitmanAdapter::class);
    $services->set(UmeetUpravlyatProektamiWorkspace::class, UnitmanAdapter::class);
    $services->set(UmeetPoluchatTokenRedaktirovaniyaUnita::class, UnitmanAdapter::class);
    $services->set(UmeetUpravlyatFaylamiWorkspace::class, MediatekaFaylyWorkspaceAdapter::class);
    $services->set(UmeetPoluchatNavyki::class, SkillAdapter::class);

    // Имя артефакта: allowlist из env, лимит длины — дефолт конструктора.
    $services->set(ImyaFajlaHoda::class)
        ->arg('$allowedExtensions', '%env(OPENCODE_ARTIFACT_ALLOWED_EXT)%');
};
