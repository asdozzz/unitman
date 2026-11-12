<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita\VolumeZapros;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\PeremenieUnitaZapolneni;
use App\Unitman\Business\Model\Unit\VolumeValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Тома юнита независимы от значений переменных: клиент передаёт `dir`
 * (ключ тома из `unitman_volumes`) и `fileId` загруженного архива.
 */
final class ZapolnitPeremenieUnitaVolumesTest extends TestCase
{
    private const CONFIG = [
        'variables' => [
            ['id' => 'APP_TITLE', 'label' => 'APP_TITLE', 'type' => 'string', 'defaultValue' => 'title'],
            ['id' => 'DB_HOST', 'label' => 'DB_HOST', 'type' => 'string', 'defaultValue' => 'localhost'],
        ],
        'prepare' => ['ls -la'],
        'reset_prepare' => ['ls -la'],
        'up' => ['ls -la'],
        'down' => ['ls -la'],
        'services' => [
            'web' => [
                'ports' => [
                    ['port' => 8081, 'type' => 'http', 'startUri' => '/test'],
                ],
            ],
        ],
        'unitman_volumes' => [
            ['dir' => 'db', 'name' => 'БД'],
            ['dir' => 'web', 'name' => 'Что то ещё'],
        ],
    ];

    private const VALUES = ['APP_TITLE' => 'unit', 'DB_HOST' => 'localhost'];

    #[Test]
    public function toma_zapolnyayutsya_po_dir_iz_konfiga(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie(self::VALUES, [new VolumeZapros('db', 'file-1')]);

        $volumes = $unit->poluchitVolumeUnita();

        $this->assertCount(2, $volumes);
        $this->assertInstanceOf(VolumeValue::class, $volumes[0]);
        $this->assertSame('db', $volumes[0]->getDir());
        $this->assertSame('БД', $volumes[0]->getName());
        $this->assertSame('file-1', $volumes[0]->getFileId());
        $this->assertTrue($volumes[0]->esliSFaylom());

        $this->assertSame('web', $volumes[1]->getDir());
        $this->assertSame('Что то ещё', $volumes[1]->getName());
        $this->assertNull($volumes[1]->getFileId());
        $this->assertFalse($volumes[1]->esliSFaylom());
    }

    /**
     * Без переданных томов файлы не появляются.
     */
    #[Test]
    public function bez_zaprosa_tomov_fayly_pustye(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie(self::VALUES);

        $volumes = $unit->poluchitVolumeUnita();

        $this->assertCount(2, $volumes);
        $this->assertNull($volumes[0]->getFileId());
        $this->assertNull($volumes[1]->getFileId());
    }

    #[Test]
    public function event_zapolneniya_soderzhit_toma(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie(self::VALUES, [new VolumeZapros('db', 'file-1')]);

        $event = $this->posledneeZapolnenie($unit);

        $this->assertSame([
            ['dir' => 'db', 'name' => 'БД', 'fileId' => 'file-1'],
            ['dir' => 'web', 'name' => 'Что то ещё', 'fileId' => null],
        ], $event->volumes);
    }

    #[Test]
    public function pri_povtornom_zapolnenii_toma_pereschityvayutsya(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie(self::VALUES, [new VolumeZapros('db', 'file-1')]);
        $unit->zapolnitPeremenie(self::VALUES, [new VolumeZapros('db', 'file-2')]);

        $volumes = $unit->poluchitVolumeUnita();

        $this->assertSame('file-2', $volumes[0]->getFileId());
        $this->assertSame('file-2', $this->posledneeZapolnenie($unit)->volumes[0]['fileId']);
    }

    /**
     * Том, не переданный в запросе, сохраняет текущий файл.
     */
    #[Test]
    public function ne_peredannii_tom_sohranyaet_fayl(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie(self::VALUES, [new VolumeZapros('db', 'file-1')]);
        $unit->zapolnitPeremenie(['APP_TITLE' => 'unit-2', 'DB_HOST' => 'localhost']);

        $this->assertSame('file-1', $unit->poluchitVolumeUnita()[0]->getFileId());
    }

    /**
     * Явный `fileId: null` очищает файл тома.
     */
    #[Test]
    public function yavnii_null_ochishchaet_fayl_toma(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie(self::VALUES, [new VolumeZapros('db', 'file-1')]);
        $unit->zapolnitPeremenie(self::VALUES, [new VolumeZapros('db', null)]);

        $this->assertNull($unit->poluchitVolumeUnita()[0]->getFileId());
    }

    /**
     * Если переданы только тома, значения переменных сохраняются.
     */
    #[Test]
    public function pustie_znacheniya_sohranyayut_peremennie(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie(self::VALUES);
        $unit->zapolnitPeremenie([], [new VolumeZapros('web', 'file-web')]);

        $values = [];
        foreach ($unit->poluchitZnacheniyaPeremenih() as $variableValue) {
            $values[$variableValue->getId()] = $variableValue->getValue();
        }

        $this->assertSame(self::VALUES, $values);
        $this->assertSame('file-web', $unit->poluchitVolumeUnita()[1]->getFileId());
    }

    #[Test]
    public function neizvestnii_dir_otklonyaetsya(): void
    {
        $unit = $this->unitSKonfigom();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.volumes.dir_not_found');

        $unit->zapolnitPeremenie(self::VALUES, [new VolumeZapros('unknown', 'file-1')]);
    }

    #[Test]
    public function povtornii_dir_v_zaprose_otklonyaetsya(): void
    {
        $unit = $this->unitSKonfigom();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.volumes.dir_is_not_unique');

        $unit->zapolnitPeremenie(
            self::VALUES,
            [new VolumeZapros('db', 'file-1'), new VolumeZapros('db', 'file-2')]
        );
    }

    #[Test]
    public function bez_bloka_unitman_volumes_toma_pustye(): void
    {
        $unit = $this->unitSKonfigomBezVolumes();

        $unit->zapolnitPeremenie(self::VALUES);

        $this->assertSame([], $unit->poluchitVolumeUnita());
        $this->assertSame([], $this->posledneeZapolnenie($unit)->volumes);
    }

    private function unitSKonfigom(): Unit
    {
        $unit = $this->noviiUnit();

        $unit->applyKonfigUnitaUstanovlen(new KonfigUnitaUstanovlen('unit-id', self::CONFIG));

        return $unit;
    }

    private function unitSKonfigomBezVolumes(): Unit
    {
        $config = self::CONFIG;
        unset($config['unitman_volumes']);

        $unit = $this->noviiUnit();

        $unit->applyKonfigUnitaUstanovlen(new KonfigUnitaUstanovlen('unit-id', $config));

        return $unit;
    }

    private function noviiUnit(): Unit
    {
        return Unit::sozdatUnit(
            'unit-id',
            'author-id',
            'prozes-id',
            new SozdatUnit('project-id', 'unit-name', 'feature/1', [], 3000)
        );
    }

    private function posledneeZapolnenie(Unit $unit): PeremenieUnitaZapolneni
    {
        $result = null;

        foreach ($unit->releaseEvents() as $event) {
            if ($event instanceof PeremenieUnitaZapolneni) {
                $result = $event;
            }
        }

        $this->assertNotNull($result, 'Ожидалось событие PeremenieUnitaZapolneni');

        /** @var PeremenieUnitaZapolneni $result */
        return $result;
    }
}
