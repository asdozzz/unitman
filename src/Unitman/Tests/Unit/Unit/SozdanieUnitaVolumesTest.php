<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit\VolumeZapros;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\VolumeValue;
use PHPUnit\Framework\TestCase;

/**
 * Тома, выбранные клиентом при создании юнита, попадают в событие `UnitSozdan`
 * и доступны через агрегат сразу после создания.
 */
final class SozdanieUnitaVolumesTest extends TestCase
{
    /**
     * @test
     */
    public function toma_iz_zaprosa_popadayut_v_event_i_v_agregat(): void
    {
        $unit = $this->sozdatUnit([
            new VolumeZapros('/var/www/db', 'БД', 'file-1'),
            new VolumeZapros('/var/www/web', 'Фронт'),
        ]);

        $event = $this->posledneeSozdanie($unit);

        $this->assertSame([
            ['dir' => '/var/www/db', 'name' => 'БД', 'fileId' => 'file-1'],
            ['dir' => '/var/www/web', 'name' => 'Фронт', 'fileId' => null],
        ], $event->volumes);

        $volumes = $unit->poluchitVolumeUnita();

        $this->assertCount(2, $volumes);
        $this->assertInstanceOf(VolumeValue::class, $volumes[0]);
        $this->assertSame('/var/www/db', $volumes[0]->getDir());
        $this->assertSame('БД', $volumes[0]->getName());
        $this->assertSame('file-1', $volumes[0]->getFileId());
        $this->assertTrue($volumes[0]->esliSFaylom());

        $this->assertSame('/var/www/web', $volumes[1]->getDir());
        $this->assertSame('Фронт', $volumes[1]->getName());
        $this->assertNull($volumes[1]->getFileId());
        $this->assertFalse($volumes[1]->esliSFaylom());
    }

    /**
     * @test
     */
    public function bez_tomov_sobitie_soderzhit_pustoi_massiv(): void
    {
        $unit = $this->sozdatUnit();

        $this->assertSame([], $this->posledneeSozdanie($unit)->volumes);
        $this->assertSame([], $unit->poluchitVolumeUnita());
    }

    /**
     * @test
     */
    public function dublikat_dir_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.volumes.dir_is_not_unique');

        $this->sozdatUnit([
            new VolumeZapros('/var/www/db', 'БД', 'file-1'),
            new VolumeZapros('/var/www/db', 'БД 2', 'file-2'),
        ]);
    }

    /**
     * @test
     */
    public function pustoie_imia_toma_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.volume_value.name_is_empty');

        $this->sozdatUnit([
            new VolumeZapros('/var/www/db', ''),
        ]);
    }

    /**
     * @param VolumeZapros[] $volumes
     */
    private function sozdatUnit(array $volumes = []): Unit
    {
        return Unit::sozdatUnit(
            'unit-id',
            'author-id',
            'prozes-id',
            new SozdatUnit('project-id', 'unit-name', 'feature/1', [], 3000, $volumes)
        );
    }

    private function posledneeSozdanie(Unit $unit): UnitSozdan
    {
        $result = null;

        foreach ($unit->releaseEvents() as $event) {
            if ($event instanceof UnitSozdan) {
                $result = $event;
            }
        }

        $this->assertInstanceOf(UnitSozdan::class, $result);

        return $result;
    }
}
