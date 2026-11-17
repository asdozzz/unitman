<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Repository\Unit;

use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Infra\Repository\Unit\SqlZanyatostUnitaRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use PHPUnit\Framework\Attributes\Test;

/**
 * Захват занятости юнита на реальной СУБД.
 *
 * Здесь проверяется именно то, что нельзя проверить моками: атомарное
 * разрешение конфликта по первичному ключу и сравнение TTL по часам базы.
 * Каждый тест работает в транзакции, откатываемой в tearDown.
 */
final class SqlZanyatostUnitaRepositoryTest extends AbstractTestCaseWithTransactionWrapper
{
    private const UNIT_ID = 'unit-zanyatost-1';
    private const VLADELEC = 'opencode:turn-1';

    private SqlZanyatostUnitaRepository $repository;

    function setUp(): void
    {
        parent::setUp();

        $this->repository = new SqlZanyatostUnitaRepository(self::$db);
    }

    #[Test]
    public function povtornii_zahvat_do_ischecheniya_ttl_otklonyaetsya(): void
    {
        $ttl = (new \DateTimeImmutable())->modify('+20 minutes');

        self::assertTrue($this->repository->zahvatit(self::UNIT_ID, self::VLADELEC, TipZanyatosti::OPENCODE_HOD, $ttl));

        self::assertFalse(
            $this->repository->zahvatit(self::UNIT_ID, 'opencode:turn-2', TipZanyatosti::OPENCODE_HOD, $ttl),
            'второй захват не должен проходить до истечения TTL',
        );

        $zanyatost = $this->repository->poluchitAktivnuyu(self::UNIT_ID);

        self::assertNotNull($zanyatost);
        self::assertSame(self::VLADELEC, $zanyatost->vladelec);
    }

    #[Test]
    public function zahvat_posle_ischecheniya_ttl_perehodit_novomu_vladelcu(): void
    {
        $this->vstavitZahvat(self::UNIT_ID, 'opencode:turn-1', '-5 minutes');

        $zahvacheno = $this->repository->zahvatit(
            self::UNIT_ID,
            'opencode:turn-2',
            TipZanyatosti::PROZES,
            (new \DateTimeImmutable())->modify('+20 minutes'),
        );

        self::assertTrue($zahvacheno, 'просроченный захват должен перезаписываться');

        $zanyatost = $this->repository->poluchitAktivnuyu(self::UNIT_ID);

        self::assertNotNull($zanyatost);
        self::assertSame('opencode:turn-2', $zanyatost->vladelec);
        self::assertSame(TipZanyatosti::PROZES, $zanyatost->tip);
    }

    #[Test]
    public function osvobozhdenie_chuzhim_vladelcem_nichego_ne_menyaet(): void
    {
        $this->repository->zahvatit(
            self::UNIT_ID,
            self::VLADELEC,
            TipZanyatosti::OPENCODE_HOD,
            (new \DateTimeImmutable())->modify('+20 minutes'),
        );

        $this->repository->osvobodit(self::UNIT_ID, 'opencode:turn-2');

        $zanyatost = $this->repository->poluchitAktivnuyu(self::UNIT_ID);

        self::assertNotNull($zanyatost);
        self::assertSame(self::VLADELEC, $zanyatost->vladelec);
    }

    #[Test]
    public function osvobozhdenie_svoim_vladelcem_snimaet_zahvat(): void
    {
        $this->repository->zahvatit(
            self::UNIT_ID,
            self::VLADELEC,
            TipZanyatosti::OPENCODE_HOD,
            (new \DateTimeImmutable())->modify('+20 minutes'),
        );

        $this->repository->osvobodit(self::UNIT_ID, self::VLADELEC);

        self::assertNull($this->repository->poluchitAktivnuyu(self::UNIT_ID));
    }

    #[Test]
    public function osvobozhdenie_otsutstvuyushego_zahvata_idempotentno(): void
    {
        $this->repository->osvobodit(self::UNIT_ID, self::VLADELEC);

        self::assertNull($this->repository->poluchitAktivnuyu(self::UNIT_ID));
    }

    #[Test]
    public function aktivnaya_zanyatost_ne_vidna_posle_ischecheniya_ttl(): void
    {
        $this->vstavitZahvat(self::UNIT_ID, self::VLADELEC, '-1 minute');

        self::assertNull(
            $this->repository->poluchitAktivnuyu(self::UNIT_ID),
            'просроченная строка не считается активной занятостью',
        );
    }

    #[Test]
    public function udalenie_prosrochennih_zahvatov_schitaet_tolko_prosrochennie(): void
    {
        $this->vstavitZahvat(self::UNIT_ID, self::VLADELEC, '-10 minutes');
        $this->vstavitZahvat('unit-zanyatost-2', 'opencode:turn-2', '-1 day');
        $this->repository->zahvatit(
            'unit-zanyatost-3',
            self::VLADELEC,
            TipZanyatosti::OPENCODE_HOD,
            (new \DateTimeImmutable())->modify('+20 minutes'),
        );

        $udaleno = $this->repository->udalitProsrochennie(new \DateTimeImmutable());

        self::assertGreaterThanOrEqual(2, $udaleno);
        self::assertNotNull(
            $this->repository->poluchitAktivnuyu('unit-zanyatost-3'),
            'активный захват не должен удаляться',
        );
    }

    #[Test]
    public function repozitorii_konfiguriruet_odin_i_tot_zhe_klass(): void
    {
        $izKonteinera = self::$container->get(ZanyatostUnitaRepository::class);

        self::assertInstanceOf(SqlZanyatostUnitaRepository::class, $izKonteinera);
    }

    /**
     * Кладёт строку захвата с произвольным сроком истечения, минуя upsert-логику,
     * чтобы получить гарантированно просроченное состояние на часах базы.
     */
    private function vstavitZahvat(string $unitId, string $vladelec, string $istekaetOtnositelno): void
    {
        $istekaetV = (new \DateTimeImmutable())->modify($istekaetOtnositelno);

        self::$db->executeStatement(
            'insert into ' . SqlZanyatostUnitaRepository::TABLE
            . ' (unit_id, vladelec, tip, zahvacheno_v, istekaet_v)
              values (:unitId, :vladelec, :tip, now(), :istekaetV)
              on conflict (unit_id) do update
                  set vladelec = excluded.vladelec,
                      tip = excluded.tip,
                      zahvacheno_v = excluded.zahvacheno_v,
                      istekaet_v = excluded.istekaet_v',
            [
                'unitId' => $unitId,
                'vladelec' => $vladelec,
                'tip' => TipZanyatosti::OPENCODE_HOD->value,
                'istekaetV' => $istekaetV->format('Y-m-d H:i:sP'),
            ]
        );
    }

}
