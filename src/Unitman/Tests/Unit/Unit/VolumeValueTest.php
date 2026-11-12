<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Model\Unit\VolumeValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Значение тома юнита: описание из `unitman_volumes` плюс ид файла медиатеки.
 */
final class VolumeValueTest extends TestCase
{
    #[Test]
    public function tom_bez_fayla_sozdaetsya(): void
    {
        $volume = new VolumeValue('db', 'БД');

        $this->assertSame('db', $volume->getDir());
        $this->assertSame('БД', $volume->getName());
        $this->assertNull($volume->getFileId());
        $this->assertFalse($volume->esliSFaylom());
    }

    #[Test]
    public function tom_s_faylom_sozdaetsya(): void
    {
        $volume = new VolumeValue('db', 'БД', 'file-1');

        $this->assertSame('file-1', $volume->getFileId());
        $this->assertTrue($volume->esliSFaylom());
        $this->assertSame(
            ['dir' => 'db', 'name' => 'БД', 'fileId' => 'file-1'],
            $volume->toArray()
        );
    }

    #[Test]
    public function dir_i_name_obrezayutsya(): void
    {
        $volume = new VolumeValue('  db  ', '  БД  ', '  file-1  ');

        $this->assertSame('db', $volume->getDir());
        $this->assertSame('БД', $volume->getName());
        $this->assertSame('file-1', $volume->getFileId());
    }

    #[Test]
    public function pustoi_dir_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.volume_value.dir_is_empty');

        new VolumeValue('   ', 'БД');
    }

    #[Test]
    public function pustoe_imya_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.volume_value.name_is_empty');

        new VolumeValue('db', '');
    }

    #[Test]
    public function pustoi_id_fayla_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.volume_value.file_id_is_empty');

        new VolumeValue('db', 'БД', '  ');
    }

    #[Test]
    public function vosstanovlenie_iz_massiva_s_faylom(): void
    {
        $volume = VolumeValue::fromArray(['dir' => 'web', 'name' => 'Фронт', 'fileId' => 'file-2']);

        $this->assertSame('web', $volume->getDir());
        $this->assertSame('Фронт', $volume->getName());
        $this->assertSame('file-2', $volume->getFileId());
    }

    #[Test]
    public function vosstanovlenie_iz_massiva_bez_fayla(): void
    {
        // Совместимость с событиями, записанными до появления volumes.
        $volume = VolumeValue::fromArray(['dir' => 'web', 'name' => 'Фронт']);

        $this->assertNull($volume->getFileId());
        $this->assertFalse($volume->esliSFaylom());
    }

    #[Test]
    public function vosstanovlenie_bez_dir_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.volume_value.dir_is_empty');

        VolumeValue::fromArray(['name' => 'Фронт']);
    }

    #[Test]
    public function krughovoi_cikl_toarray_fromarray(): void
    {
        $volume = new VolumeValue('db', 'БД', 'file-1');

        $restored = VolumeValue::fromArray($volume->toArray());

        $this->assertSame($volume->toArray(), $restored->toArray());
    }
}
