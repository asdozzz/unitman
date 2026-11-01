<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\PeremenieUnitaZapolneni;
use App\Unitman\Business\Model\Unit\VolumeValue;
use PHPUnit\Framework\TestCase;

/**
 * Тома юнита собираются из блока `unitman_volumes` и заполненных значений переменных:
 * ид файла медиатеки берётся из переменной, объявленной в томе.
 */
final class ZapolnitPeremenieUnitaVolumesTest extends TestCase
{
    private const CONFIG = [
        'variables' => [
            ['id' => 'APP_TITLE', 'label' => 'APP_TITLE', 'type' => 'string', 'defaultValue' => 'title'],
            ['id' => 'DB_FILE', 'label' => 'DB_FILE', 'type' => 'string', 'defaultValue' => ''],
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
            ['dir' => 'db', 'name' => 'БД', 'variable' => 'DB_FILE'],
            ['dir' => 'web', 'name' => 'Что то ещё'],
        ],
    ];

    /**
     * @test
     */
    public function toma_sobirayutsya_iz_konfiga_i_znachenii(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie([
            'APP_TITLE' => 'unit',
            'DB_FILE' => 'file-1',
        ]);

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
     * @test
     */
    public function event_zapolneniya_soderzhit_toma(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie([
            'APP_TITLE' => 'unit',
            'DB_FILE' => 'file-1',
        ]);

        $event = $this->posledneeZapolnenie($unit);

        $this->assertSame([
            ['dir' => 'db', 'name' => 'БД', 'fileId' => 'file-1'],
            ['dir' => 'web', 'name' => 'Что то ещё', 'fileId' => null],
        ], $event->volumes);
    }

    /**
     * @test
     */
    public function pri_povtornom_zapolnenii_toma_pereschityvayutsya(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie([
            'APP_TITLE' => 'unit',
            'DB_FILE' => 'file-1',
        ]);

        $unit->zapolnitPeremenie([
            'APP_TITLE' => 'unit',
            'DB_FILE' => 'file-2',
        ]);

        $volumes = $unit->poluchitVolumeUnita();

        $this->assertSame('file-2', $volumes[0]->getFileId());
        $this->assertSame('file-2', $this->posledneeZapolnenie($unit)->volumes[0]['fileId']);
    }

    /**
     * Пустое значение переменной тома ловится общей валидацией значений конфига.
     *
     * @test
     */
    public function pustoe_znachenie_peremennoi_toma_otklonyaetsya(): void
    {
        $unit = $this->unitSKonfigom();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.variable_value.value_is_empty');

        $unit->zapolnitPeremenie([
            'APP_TITLE' => 'unit',
            'DB_FILE' => '',
        ]);
    }

    /**
     * @test
     */
    public function otsutstvuyushchee_znachenie_peremennoi_toma_otklonyaetsya(): void
    {
        $unit = $this->unitSKonfigom();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('DB_FILE');

        $unit->zapolnitPeremenie([
            'APP_TITLE' => 'unit',
        ]);
    }

    /**
     * @test
     */
    public function bez_bloka_unitman_volumes_toma_pustye(): void
    {
        $unit = $this->unitSKonfigomBezVolumes();

        $unit->zapolnitPeremenie([
            'APP_TITLE' => 'unit',
            'DB_FILE' => 'file-1',
        ]);

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
