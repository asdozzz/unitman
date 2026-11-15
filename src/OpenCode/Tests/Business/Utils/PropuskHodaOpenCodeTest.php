<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Utils\PropuskHodaOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Гейт старта хода: свободная сессия ходит без обращения к Unitman,
 * привязанная — только когда готовы ВСЕ её юниты. Удалённые юниты выпадают
 * из хода, а когда удалены все — сессия архивируется.
 */
final class PropuskHodaOpenCodeTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private UmeetUpravlyatUnitami&MockObject $unitami;

    private SessiyaOpenCodeRepository&MockObject $repository;

    private PropuskHodaOpenCode $propusk;

    protected function setUp(): void
    {
        $this->unitami = $this->createMock(UmeetUpravlyatUnitami::class);
        $this->repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $this->propusk = new PropuskHodaOpenCode($this->unitami, $this->repository);
    }

    #[Test]
    public function svobodnaya_sessiya_propuskaetsya_bez_obrashcheniya_k_unitman(): void
    {
        $this->unitami->expects($this->never())->method('poluchitSostoyanieUnita');
        $this->repository->expects($this->never())->method('update');

        $this->assertSame([], $this->propusk->proverit($this->svobodnayaSessiya()));
    }

    #[Test]
    public function gotovyi_edinstvennyi_unit_propuskaet_hod(): void
    {
        $this->unitami->expects($this->once())
            ->method('poluchitSostoyanieUnita')
            ->with('unit-1')
            ->willReturn($this->sostoyanie(SostoyanieUnita::GROUP_READY));
        $this->repository->expects($this->never())->method('update');

        $zhivye = $this->propusk->proverit($this->sessiyaSUnitami(['unit-1']));

        $this->assertContainsOnlyInstancesOf(PrivyazkaKUnitu::class, $zhivye);
        $this->assertSame(['unit-1'], array_map(
            static fn (PrivyazkaKUnitu $privyazka): string => $privyazka->unitId,
            $zhivye,
        ));
    }

    #[Test]
    public function vse_gotovye_unity_propuskayut_hod_v_poryadke_privezki(): void
    {
        $this->unitami->method('poluchitSostoyanieUnita')
            ->willReturnMap([
                ['unit-1', $this->sostoyanie(SostoyanieUnita::GROUP_READY)],
                ['unit-2', $this->sostoyanie(SostoyanieUnita::GROUP_READY)],
                ['unit-3', $this->sostoyanie(SostoyanieUnita::GROUP_READY)],
            ]);
        $this->repository->expects($this->never())->method('update');

        $zhivye = $this->propusk->proverit($this->sessiyaSUnitami(['unit-1', 'unit-2', 'unit-3']));

        $this->assertSame(
            ['unit-1', 'unit-2', 'unit-3'],
            array_map(static fn (PrivyazkaKUnitu $privyazka): string => $privyazka->unitId, $zhivye),
        );
    }

    #[Test]
    public function odin_negotovyi_unit_blokiruet_hod_dazhe_esli_ostalnye_gotovy(): void
    {
        $this->unitami->method('poluchitSostoyanieUnita')
            ->willReturnMap([
                ['unit-1', $this->sostoyanie(SostoyanieUnita::GROUP_READY)],
                ['unit-2', $this->sostoyanie(SostoyanieUnita::GROUP_BLOCKED)],
            ]);
        $this->repository->expects($this->never())->method('update');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.unit_not_ready');

        $this->propusk->proverit($this->sessiyaSUnitami(['unit-1', 'unit-2']));
    }

    #[Test]
    public function udalennyi_unit_isklyuchaetsya_iz_hoda_no_ne_arhiviruet_sessiyu(): void
    {
        $this->unitami->method('poluchitSostoyanieUnita')
            ->willReturnMap([
                ['unit-1', $this->sostoyanie(SostoyanieUnita::GROUP_ARCHIVED)],
                ['unit-2', $this->sostoyanie(SostoyanieUnita::GROUP_READY)],
            ]);
        $this->repository->expects($this->never())->method('update');

        $session = $this->sessiyaSUnitami(['unit-1', 'unit-2']);

        $zhivye = $this->propusk->proverit($session);

        $this->assertSame(
            ['unit-2'],
            array_map(static fn (PrivyazkaKUnitu $privyazka): string => $privyazka->unitId, $zhivye),
        );
        $this->assertFalse($session->estArhiv());
    }

    #[Test]
    public function vse_unity_udalenny_arhiviruet_sessiyu_i_blokiruet_hod(): void
    {
        $session = $this->sessiyaSUnitami(['unit-1', 'unit-2']);

        $this->unitami->method('poluchitSostoyanieUnita')
            ->willReturn($this->sostoyanie(SostoyanieUnita::GROUP_ARCHIVED));

        $this->repository->expects($this->once())
            ->method('update')
            ->with($this->identicalTo($session));

        try {
            $this->propusk->proverit($session);
            $this->fail('Ожидалось исключение archived');
        } catch (\DomainException $e) {
            $this->assertSame('opencode.session.archived', $e->getMessage());
        }

        $this->assertTrue($session->estArhiv());
        $this->assertSame(SessiyaOpenCode::ARCHIVE_REASON_UNIT_DELETED, $session->getArchiveReason());
    }

    #[Test]
    public function arhivnaya_sessiya_ne_propuskaet_hod(): void
    {
        $session = $this->sessiyaSUnitami(['unit-1']);
        $session->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, self::NOW);

        $this->unitami->expects($this->never())->method('poluchitSostoyanieUnita');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.archived');

        $this->propusk->proverit($session);
    }

    private function sostoyanie(string $group): SostoyanieUnita
    {
        return new SostoyanieUnita(true, 'USPESHNO_ZAPUSHEN', 'project-1', 'unit', 'main', $group);
    }

    private function svobodnayaSessiya(): SessiyaOpenCode
    {
        return SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, [], self::NOW);
    }

    /**
     * К сессии можно привязать только один юнит на проект, поэтому каждый
     * юнит фикстуры получает собственный проект.
     *
     * @param list<string> $unitIds
     */
    private function sessiyaSUnitami(array $unitIds): SessiyaOpenCode
    {
        return SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            array_map(
                static fn (string $unitId): PrivyazkaKUnitu => PrivyazkaKUnitu::izZnacheniy(
                    $unitId,
                    sprintf('project-%s', substr($unitId, strlen('unit-'))),
                ),
                $unitIds,
            ),
            self::NOW,
        );
    }
}
