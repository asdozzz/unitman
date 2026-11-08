<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Utils\ImyaFaylaDlyaDockerVolume;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Имя файла медиатеки должно быть пригодно для имени docker-тома.
 */
final class ImyaFaylaDlyaDockerVolumeTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function imenaProvider(): array
    {
        return [
            'латиница без изменений' => ['dump.zip', 'dump.zip'],
            'кириллица транслитерируется' => ['Дамп БД.zip', 'damp-bd.zip'],
            'верхний регистр приводится к нижнему' => ['Dump.ZIP', 'dump.zip'],
            'пробелы и скобки — разделители' => ['my dump (final).zip', 'my-dump-final.zip'],
            'подчёркивания сохраняются' => ['unit_1.zip', 'unit_1.zip'],
            'несколько точек схлопываются' => ['a..b.zip', 'a.b.zip'],
            'разделители пути вычищаются' => ['../etc/passwd.zip', 'etc-passwd.zip'],
            'обратные слеши вычищаются' => ['a/b\\c.zip', 'a-b-c.zip'],
            'ведущие и хвостовые разделители срезаются' => ['__--dump.zip--__', 'dump.zip'],
            'диакритика транслитерируется' => ['Ünïcode Näme.zip', 'unicode-name.zip'],
            'спецсимволы становятся дефисом' => ['a@b#c$.zip', 'a-b-c.zip'],
            'пустая строка — значение по умолчанию' => ['', 'file'],
            'только спецсимволы — значение по умолчанию' => ['***', 'file'],
            'только кириллица без расширения' => ['Дамп', 'damp'],
            'односимвольное имя дополняется' => ['a', 'a-file'],
            'односимвольное имя с расширением дополняется' => ['a.zip', 'a.zip'],
        ];
    }

    #[DataProvider('imenaProvider')]
    public function test_normalizaciya_imeni(string $ishodnoe, string $ozhidaemoe): void
    {
        $this->assertSame($ozhidaemoe, ImyaFaylaDlyaDockerVolume::normalizovat($ishodnoe));
    }

    public function test_rezultat_sootvetstvuet_pravilam_docker(): void
    {
        $imena = [
            'Дамп БД (v2).ZIP',
            '  ..  ',
            'a/b/c.tar.gz',
            'Ёжик_2024.zip',
            str_repeat('очень-длинное-имя-', 20) . '.zip',
        ];

        foreach ($imena as $imya) {
            $rezultat = ImyaFaylaDlyaDockerVolume::normalizovat($imya);

            $this->assertTrue(
                ImyaFaylaDlyaDockerVolume::etoDopustimoeImya($rezultat),
                sprintf('Имя "%s" недопустимо для docker-тома', $rezultat)
            );
        }
    }

    public function test_dlinnoe_imya_obrezaetsya_s_sokhraneniem_rasshireniya(): void
    {
        $imya = str_repeat('a', 200) . '.zip';

        $rezultat = ImyaFaylaDlyaDockerVolume::normalizovat($imya);

        $this->assertLessThanOrEqual(ImyaFaylaDlyaDockerVolume::MAX_LENGTH, strlen($rezultat));
        $this->assertStringEndsWith('.zip', $rezultat);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function imenaArhivaProvider(): array
    {
        return [
            'расширение уже есть' => ['dump.zip', 'dump.zip'],
            'расширение дополняется' => ['dump', 'dump.zip'],
            'кириллица и отсутствие расширения' => ['Дамп БД', 'damp-bd.zip'],
            'верхний регистр расширения' => ['Dump.ZIP', 'dump.zip'],
            'пустое имя — значение по умолчанию с расширением' => ['', 'file.zip'],
            'односимвольное имя дополняется и получает расширение' => ['a', 'a-file.zip'],
        ];
    }

    #[DataProvider('imenaArhivaProvider')]
    public function test_normalizaciya_imeni_arhiva(string $ishodnoe, string $ozhidaemoe): void
    {
        $rezultat = ImyaFaylaDlyaDockerVolume::normalizovatImyaArhiva($ishodnoe);

        $this->assertSame($ozhidaemoe, $rezultat);
        $this->assertTrue(ImyaFaylaDlyaDockerVolume::etoDopustimoeImya($rezultat));
    }

    public function test_bityi_utf8_ne_privodit_k_oshibke(): void
    {
        $rezultat = ImyaFaylaDlyaDockerVolume::normalizovat("ab\xC3(cd.zip");

        $this->assertTrue(ImyaFaylaDlyaDockerVolume::etoDopustimoeImya($rezultat));
        $this->assertSame('ab-cd.zip', $rezultat);
    }

    public function test_nulevoi_bait_udalyayetsya(): void
    {
        $this->assertSame('dump.zip', ImyaFaylaDlyaDockerVolume::normalizovat("dump\0.zip"));
    }
}
