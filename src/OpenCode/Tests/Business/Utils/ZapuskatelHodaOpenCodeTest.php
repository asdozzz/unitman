<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\IstochnikRekvizitovOpenCode;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetPoluchatTokenRedaktirovaniyaUnita;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Utils\OpredelitelModeliHodaOpenCode;
use App\OpenCode\Business\Utils\OpredelitelRezhimaHodaOpenCode;
use App\OpenCode\Business\Utils\VladelecZanyatostiOpenCode;
use App\OpenCode\Business\Utils\ZapuskatelHodaOpenCode;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaProektamiWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Захват юнитов при старте хода, освобождение при отказе и режим хода (15.7-а).
 *
 * Главный инвариант: если раннер не принял ход, юнит не должен остаться
 * занятым до истечения TTL. Обратная ошибка (освободить чужой захват) тоже
 * недопустима — освобождаем только то, что захватили сами.
 *
 * Режим: RW только для автора/редактора при включённом редактировании и
 * незащищённой ветке; в этом случае запрашивается личный токен. RO-ход
 * токен не запрашивает вовсе.
 */
final class ZapuskatelHodaOpenCodeTest extends TestCase
{
    private const TURN_ID = 'turn-1';
    private const WORKFLOW_ID = 'workflow-1';
    private const ACCOUNT_ID = 'account-1';

    #[Test]
    public function yuniti_zahvatyvayutsya_do_starta_hoda(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())
            ->method('zahvatit')
            ->with(['unit-1', 'unit-2'], VladelecZanyatostiOpenCode::dlyaHoda(self::TURN_ID));
        $bronirovanie->expects($this->never())->method('osvobodit');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('save');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->once())->method('update');

        $zapuskatel = $this->zapuskatel(
            $turnRepository,
            $sessionRepository,
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $bronirovanie,
        );

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    #[Test]
    public function otkaz_rannera_osvobozhdaet_zahvat(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())->method('zahvatit');
        $bronirovanie->expects($this->once())
            ->method('osvobodit')
            ->with(['unit-1', 'unit-2'], VladelecZanyatostiOpenCode::dlyaHoda(self::TURN_ID));

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->willThrowException(new \RuntimeException('temporal unavailable'));

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->never())->method('save');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->never())->method('update');

        $zapuskatel = $this->zapuskatel(
            $turnRepository,
            $sessionRepository,
            $runner,
            $bronirovanie,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('temporal unavailable');

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    #[Test]
    public function otkaz_samogo_zahvata_ne_privodit_k_osvobozhdeniyu(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())
            ->method('zahvatit')
            ->willThrowException(new \DomainException('unit.zanyat'));
        $bronirovanie->expects($this->never())->method('osvobodit');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('nachatHod');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);

        $zapuskatel = $this->zapuskatel(
            $turnRepository,
            $this->createMock(SessiyaOpenCodeRepository::class),
            $runner,
            $bronirovanie,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.zanyat');

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    #[Test]
    public function sessiya_bez_unitei_ne_zahvatyvaet_nichego_i_ne_zaprashivaet_token(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('zahvatit');
        $bronirovanie->expects($this->never())->method('osvobodit');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('save');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->once())->method('update');

        $unitami = $this->createMock(UmeetUpravlyatUnitami::class);
        $unitami->expects($this->never())->method('poluchitSostoyanieUnita');

        $tokeny = $this->createMock(UmeetPoluchatTokenRedaktirovaniyaUnita::class);
        $tokeny->expects($this->never())->method('poluchitTokenDlyRedaktirovaniyaUnita');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                [],
                $this->anything(),
                $this->anything(),
                $this->anything(),
                null,
                false,
                null,
                $this->anything(),
            );

        $zapuskatel = $this->zapuskatel($turnRepository, $sessionRepository, $runner, $bronirovanie, $unitami, $tokeny);

        $sessiya = SessiyaOpenCode::create('session-1', self::ACCOUNT_ID, 'credential-1', null, [], 1000);

        $zapuskatel->zapustit($sessiya, 'Привет', [], []);
    }

    #[Test]
    public function workspace_ne_zahvatyvaet_unity_i_peredayot_kontekst(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('zahvatit');
        $bronirovanie->expects($this->never())->method('osvobodit');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('save');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->once())->method('update');

        $unitami = $this->createMock(UmeetUpravlyatUnitami::class);
        $unitami->expects($this->never())->method('poluchitSostoyanieUnita');

        $tokeny = $this->createMock(UmeetPoluchatTokenRedaktirovaniyaUnita::class);
        $tokeny->expects($this->never())->method('poluchitTokenDlyRedaktirovaniyaUnita');

        $zahvachennyiKontekst = null;
        $poluchenReadOnly = null;
        $poluchenToken = null;

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->willReturnCallback(
                static function (
                    string $workflowId,
                    string $accountId,
                    string $credentialId,
                    ?string $sessionId,
                    array $units,
                    string $prompt,
                    array $answers,
                    array $history,
                    ?WorkspaceKontekst $workspace = null,
                    bool $readOnly = false,
                    ?string $token = null,
                ) use (&$zahvachennyiKontekst, &$poluchenReadOnly, &$poluchenToken): string {
                    $zahvachennyiKontekst = $workspace;
                    $poluchenReadOnly = $readOnly;
                    $poluchenToken = $token;

                    return $workflowId;
                }
            );

        $zapuskatel = $this->zapuskatel($turnRepository, $sessionRepository, $runner, $bronirovanie, $unitami, $tokeny);

        $sessiya = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $zapuskatel->zapustit($sessiya, 'Первый промпт', [], []);

        $this->assertInstanceOf(WorkspaceKontekst::class, $zahvachennyiKontekst);
        $this->assertSame('workspace-1', $zahvachennyiKontekst->workspaceId);
        $this->assertFalse($zahvachennyiKontekst->readOnly);
        $this->assertFalse($poluchenReadOnly, 'Workspace не является unit-ходом: unit-режим не выставляется');
        $this->assertNull($poluchenToken, 'Workspace не получает личный токен');
        $this->assertSame(['project-1'], array_map(
            static fn ($project): string => $project->projectId,
            $zahvachennyiKontekst->projects,
        ));
        $this->assertSame('payments-refactor', $sessiya->getTitle(), 'Название workspace не подменяется промптом');
    }

    #[Test]
    public function avtor_pri_vklyuchennom_redaktirovanii_poluchaet_rw_i_token(): void
    {
        $zaprosyTokena = [];

        [$readOnly, $token] = $this->zapustitSOdnymUnitom(
            $this->sostoyanie(authorId: self::ACCOUNT_ID, editing: true),
            static function (string $unitId, string $accountId) use (&$zaprosyTokena): string {
                $zaprosyTokena[] = [$unitId, $accountId];

                return 'personal-secret';
            },
        );

        self::assertFalse($readOnly);
        self::assertSame('personal-secret', $token);
        self::assertSame([['unit-1', self::ACCOUNT_ID]], $zaprosyTokena);
    }

    #[Test]
    public function prikreplennii_redaktor_poluchaet_rw_i_svoy_token(): void
    {
        $zaprosyTokena = [];

        [$readOnly, $token] = $this->zapustitSOdnymUnitom(
            $this->sostoyanie(authorId: 'author-1', editing: true, redaktory: [self::ACCOUNT_ID]),
            static function (string $unitId, string $accountId) use (&$zaprosyTokena): string {
                $zaprosyTokena[] = [$unitId, $accountId];

                return 'editor-secret';
            },
        );

        self::assertFalse($readOnly);
        self::assertSame('editor-secret', $token);
        self::assertSame([['unit-1', self::ACCOUNT_ID]], $zaprosyTokena);
    }

    #[Test]
    public function postoronniy_hod_read_only_i_ne_zaprashivaet_token(): void
    {
        [$readOnly, $token] = $this->zapustitSOdnymUnitom(
            $this->sostoyanie(authorId: 'author-1', editing: true, redaktory: ['editor-1']),
        );

        self::assertTrue($readOnly);
        self::assertNull($token);
    }

    #[Test]
    public function vyklyuchennoe_redaktirovanie_delaet_hod_read_only(): void
    {
        [$readOnly, $token] = $this->zapustitSOdnymUnitom(
            $this->sostoyanie(authorId: self::ACCOUNT_ID, editing: false),
        );

        self::assertTrue($readOnly);
        self::assertNull($token);
    }

    #[Test]
    public function zashishennaya_vetka_delaet_hod_read_only_dazhe_dlya_avtora(): void
    {
        [$readOnly, $token] = $this->zapustitSOdnymUnitom(
            $this->sostoyanie(authorId: self::ACCOUNT_ID, editing: true, protectedBranch: true),
        );

        self::assertTrue($readOnly);
        self::assertNull($token);
    }

    #[Test]
    public function otsutstvie_lichnogo_tokena_otklonyaet_rw_hod(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('zahvatit');
        $bronirovanie->expects($this->never())->method('osvobodit');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('nachatHod');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->never())->method('save');

        $tokeny = $this->createMock(UmeetPoluchatTokenRedaktirovaniyaUnita::class);
        $tokeny->expects($this->once())
            ->method('poluchitTokenDlyRedaktirovaniyaUnita')
            ->with('unit-1', self::ACCOUNT_ID)
            ->willThrowException(new \DomainException('unit.redaktor.net_lichnogo_tokena'));

        $zapuskatel = $this->zapuskatel(
            $turnRepository,
            $this->createMock(SessiyaOpenCodeRepository::class),
            $runner,
            $bronirovanie,
            $this->unitami($this->sostoyanie(authorId: self::ACCOUNT_ID, editing: true)),
            $tokeny,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.net_lichnogo_tokena');

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], [$this->privyazka('unit-1', 'project-1')]);
    }

    #[Test]
    public function token_bez_prav_na_proekt_otklonyaet_rw_hod(): void
    {
        $tokeny = $this->createMock(UmeetPoluchatTokenRedaktirovaniyaUnita::class);
        $tokeny->expects($this->once())
            ->method('poluchitTokenDlyRedaktirovaniyaUnita')
            ->willThrowException(new \DomainException('unit.redaktor.token_bez_prav_na_proekt'));

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('nachatHod');

        $zapuskatel = $this->zapuskatel(
            $this->turnRepositoryBezSohraneniya(),
            $this->createMock(SessiyaOpenCodeRepository::class),
            $runner,
            $this->createMock(UmeetBronirovatUniti::class),
            $this->unitami($this->sostoyanie(authorId: self::ACCOUNT_ID, editing: true)),
            $tokeny,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.token_bez_prav_na_proekt');

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], [$this->privyazka('unit-1', 'project-1')]);
    }

    #[Test]
    public function esli_hotya_bu_odin_unit_read_only_to_ves_hod_read_only_i_bez_tokena(): void
    {
        $unitami = $this->createMock(UmeetUpravlyatUnitami::class);
        $unitami->method('poluchitSostoyanieUnita')->willReturnCallback(
            static function (string $unitId): SostoyanieUnita {
                if ($unitId === 'unit-2') {
                    return new SostoyanieUnita(
                        true,
                        'USPESHNO_ZAPUSHEN',
                        'project-2',
                        'unit-2',
                        'main',
                        SostoyanieUnita::GROUP_READY,
                        false,
                        null,
                        'author-1',
                        true,
                        false,
                        [],
                    );
                }

                return new SostoyanieUnita(
                    true,
                    'USPESHNO_ZAPUSHEN',
                    'project-1',
                    'unit-1',
                    'main',
                    SostoyanieUnita::GROUP_READY,
                    false,
                    null,
                    self::ACCOUNT_ID,
                    true,
                    false,
                    [],
                );
            },
        );

        $tokeny = $this->createMock(UmeetPoluchatTokenRedaktirovaniyaUnita::class);
        $tokeny->expects($this->never())->method('poluchitTokenDlyRedaktirovaniyaUnita');

        $poluchenReadOnly = null;
        $poluchenToken = 'sentinel';

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->willReturnCallback(
                static function (
                    string $workflowId,
                    string $accountId,
                    string $credentialId,
                    ?string $sessionId,
                    array $units,
                    string $prompt,
                    array $answers,
                    array $history,
                    ?WorkspaceKontekst $workspace = null,
                    bool $readOnly = false,
                    ?string $token = null,
                ) use (&$poluchenReadOnly, &$poluchenToken): string {
                    $poluchenReadOnly = $readOnly;
                    $poluchenToken = $token;

                    return $workflowId;
                }
            );

        $zapuskatel = $this->zapuskatel(
            $this->turnRepository(),
            $this->saveSessionRepository(),
            $runner,
            $this->createMock(UmeetBronirovatUniti::class),
            $unitami,
            $tokeny,
        );

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());

        self::assertTrue($poluchenReadOnly);
        self::assertNull($poluchenToken);
    }

    /**
     * Главный инвариант бюджета: ход уходит в раннер с реквизитами автора, а
     * не владельца сессии, и это же фиксируется в сохранённом ходе.
     */
    #[Test]
    public function hod_spisyvaet_rekvizity_avtora_a_ne_vladelca(): void
    {
        $poluchenAccountId = null;
        $poluchenCredentialId = null;
        $sohranennyiHod = null;

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->willReturnCallback(
                static function (
                    string $workflowId,
                    string $accountId,
                    string $credentialId,
                ) use (&$poluchenAccountId, &$poluchenCredentialId): string {
                    $poluchenAccountId = $accountId;
                    $poluchenCredentialId = $credentialId;

                    return $workflowId;
                }
            );

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(
                static function (HodOpenCode $turn) use (&$sohranennyiHod): void {
                    $sohranennyiHod = $turn;
                }
            );

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->once())->method('update');

        $zapuskatel = $this->zapuskatel(
            $turnRepository,
            $sessionRepository,
            $runner,
            $this->createMock(UmeetBronirovatUniti::class),
        );

        $zapuskatel->zapustit(
            $this->sessiya(),
            'Привет',
            [],
            [],
            null,
            new IstochnikRekvizitovOpenCode('author-2', 'cred-author', 'model-author'),
        );

        self::assertSame('author-2', $poluchenAccountId);
        self::assertSame('cred-author', $poluchenCredentialId);
        self::assertInstanceOf(HodOpenCode::class, $sohranennyiHod);
        self::assertSame('author-2', $sohranennyiHod->getAuthorAccountId());
        self::assertSame('cred-author', $sohranennyiHod->getCredentialId());
    }

    #[Test]
    public function zanyataya_sessiya_otklonyaet_vtoroi_hod(): void
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())
            ->method('zanyatSessiyu')
            ->willThrowException(new \DomainException('opencode.session.busy'));
        $turnRepository->expects($this->never())->method('save');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('nachatHod');

        $zapuskatel = $this->zapuskatel(
            $turnRepository,
            $this->createMock(SessiyaOpenCodeRepository::class),
            $runner,
            $this->createMock(UmeetBronirovatUniti::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.busy');

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    #[Test]
    public function otkaz_zahvata_unitov_osvobozhdaet_rezervaciyu_sessii(): void
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('zanyatSessiyu');
        $turnRepository->expects($this->once())
            ->method('osvoboditSessiyu')
            ->with('session-1', self::TURN_ID);
        $turnRepository->expects($this->never())->method('save');

        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())
            ->method('zahvatit')
            ->willThrowException(new \DomainException('unit.zanyat'));

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('nachatHod');

        $zapuskatel = $this->zapuskatel(
            $turnRepository,
            $this->createMock(SessiyaOpenCodeRepository::class),
            $runner,
            $bronirovanie,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.zanyat');

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    #[Test]
    public function uspeshnyi_start_ostavlyaet_rezervaciyu_do_zaversheniya(): void
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('save');
        $turnRepository->expects($this->once())->method('zanyatSessiyu');
        $turnRepository->expects($this->never())->method('osvoboditSessiyu');

        $zapuskatel = $this->zapuskatel(
            $turnRepository,
            $this->saveSessionRepository(),
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->createMock(UmeetBronirovatUniti::class),
        );

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    #[Test]
    public function otzyv_dostupa_k_proektu_blokiruet_hod_workspace(): void
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->never())->method('zanyatSessiyu');
        $turnRepository->expects($this->never())->method('save');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('nachatHod');

        $zapuskatel = $this->zapuskatel(
            $turnRepository,
            $this->createMock(SessiyaOpenCodeRepository::class),
            $runner,
            $this->createMock(UmeetBronirovatUniti::class),
            projectami: $this->projectamiBezDostupa(self::ACCOUNT_ID),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_access_revoked');

        $zapuskatel->zapustit(
            FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING),
            'Привет',
            [],
            [],
        );
    }

    #[Test]
    public function proverka_proektov_ne_zatraivaet_unit_sessii(): void
    {
        $zapuskatel = $this->zapuskatel(
            $this->turnRepository(),
            $this->saveSessionRepository(),
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->createMock(UmeetBronirovatUniti::class),
            projectami: $this->projectamiBezDostupa(self::ACCOUNT_ID),
        );

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    private function projectamiBezDostupa(string $accountId): ImitaciyaUpravleniyaProektamiWorkspace
    {
        $projectami = $this->projectami();
        $projectami->zapretitDostup($accountId);

        return $projectami;
    }

    private function zapuskatel(
        HodOpenCodeRepository $turnRepository,
        SessiyaOpenCodeRepository $sessionRepository,
        UmeetVypolnyatHodOpenCode $runner,
        UmeetBronirovatUniti $bronirovanie,
        ?UmeetUpravlyatUnitami $unitami = null,
        ?UmeetPoluchatTokenRedaktirovaniyaUnita $tokeny = null,
        ?UmeetUpravlyatProektamiWorkspace $projectami = null,
    ): ZapuskatelHodaOpenCode {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('razreshitModel')->willReturn('default-model');

        $projectami ??= $this->projectami();

        return new ZapuskatelHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $runner,
            $this->uuidGenerator(),
            $bronirovanie,
            $unitami ?? $this->unitami($this->sostoyanie(authorId: 'author-x', editing: true)),
            $tokeny ?? $this->tokeny(),
            new OpredelitelRezhimaHodaOpenCode(),
            new OpredelitelModeliHodaOpenCode($credentials),
            $projectami,
        );
    }

    private function projectami(): ImitaciyaUpravleniyaProektamiWorkspace
    {
        $projectami = new ImitaciyaUpravleniyaProektamiWorkspace();
        $projectami->zadatProekty([FabrikaWorkspace::proekt()]);

        return $projectami;
    }

    /**
     * Запускает ход с одним юнитом и возвращает, что получил раннер.
     *
     * @param callable(string, string): string|null $tokenResolver
     *
     * @return array{bool, string|null}
     */
    private function zapustitSOdnymUnitom(
        SostoyanieUnita $sostoyanie,
        ?callable $tokenResolver = null,
    ): array {
        $tokeny = $this->createMock(UmeetPoluchatTokenRedaktirovaniyaUnita::class);

        if ($tokenResolver !== null) {
            $tokeny->method('poluchitTokenDlyRedaktirovaniyaUnita')->willReturnCallback($tokenResolver);
        } else {
            $tokeny->expects($this->never())->method('poluchitTokenDlyRedaktirovaniyaUnita');
        }

        $poluchenReadOnly = null;
        $poluchenToken = 'sentinel';

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->willReturnCallback(
                static function (
                    string $workflowId,
                    string $accountId,
                    string $credentialId,
                    ?string $sessionId,
                    array $units,
                    string $prompt,
                    array $answers,
                    array $history,
                    ?WorkspaceKontekst $workspace = null,
                    bool $readOnly = false,
                    ?string $token = null,
                ) use (&$poluchenReadOnly, &$poluchenToken): string {
                    $poluchenReadOnly = $readOnly;
                    $poluchenToken = $token;

                    return $workflowId;
                }
            );

        $zapuskatel = $this->zapuskatel(
            $this->turnRepository(),
            $this->saveSessionRepository(),
            $runner,
            $this->createMock(UmeetBronirovatUniti::class),
            $this->unitami($sostoyanie),
            $tokeny,
        );

        $zapuskatel->zapustit(
            $this->sessiya(),
            'Привет',
            [],
            [$this->privyazka('unit-1', 'project-1')],
        );

        return [$poluchenReadOnly, $poluchenToken];
    }

    private function unitami(SostoyanieUnita $sostoyanie): UmeetUpravlyatUnitami
    {
        $unitami = $this->createMock(UmeetUpravlyatUnitami::class);
        $unitami->method('poluchitSostoyanieUnita')->willReturn($sostoyanie);

        return $unitami;
    }

    private function tokeny(): UmeetPoluchatTokenRedaktirovaniyaUnita
    {
        $tokeny = $this->createMock(UmeetPoluchatTokenRedaktirovaniyaUnita::class);
        $tokeny->method('poluchitTokenDlyRedaktirovaniyaUnita')->willReturn('personal-secret');

        return $tokeny;
    }

    private function turnRepository(): HodOpenCodeRepository
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('save');

        return $turnRepository;
    }

    private function turnRepositoryBezSohraneniya(): HodOpenCodeRepository
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->never())->method('save');

        return $turnRepository;
    }

    private function saveSessionRepository(): SessiyaOpenCodeRepository
    {
        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->once())->method('update');

        return $sessionRepository;
    }

    /**
     * @param list<string> $redaktory
     */
    private function sostoyanie(
        bool $exists = true,
        ?string $authorId = self::ACCOUNT_ID,
        bool $editing = true,
        bool $protectedBranch = false,
        array $redaktory = [],
    ): SostoyanieUnita {
        return new SostoyanieUnita(
            $exists,
            'USPESHNO_ZAPUSHEN',
            'project-1',
            'unit-1',
            'main',
            SostoyanieUnita::GROUP_READY,
            false,
            null,
            $authorId,
            $editing,
            $protectedBranch,
            $redaktory,
        );
    }

    private function uuidGenerator(): UuidGenerator
    {
        $generator = $this->createMock(UuidGenerator::class);
        $generator->method('makeGuid')
            ->willReturnOnConsecutiveCalls(self::TURN_ID, self::WORKFLOW_ID);

        return $generator;
    }

    /**
     * @return list<PrivyazkaKUnitu>
     */
    private function privyazki(): array
    {
        return [
            PrivyazkaKUnitu::izZnacheniy('unit-1', 'project-1'),
            PrivyazkaKUnitu::izZnacheniy('unit-2', 'project-2'),
        ];
    }

    private function privyazka(string $unitId, string $projectId): PrivyazkaKUnitu
    {
        return PrivyazkaKUnitu::izZnacheniy($unitId, $projectId);
    }

    private function sessiya(): SessiyaOpenCode
    {
        return SessiyaOpenCode::create(
            'session-1',
            self::ACCOUNT_ID,
            'credential-1',
            null,
            [$this->privyazka('unit-1', 'project-1')],
            1000,
        );
    }
}
