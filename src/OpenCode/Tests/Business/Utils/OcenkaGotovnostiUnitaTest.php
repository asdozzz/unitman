<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Utils\OcenkaGotovnostiUnita;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Матрица готовности — единственное место, где код состояния Unitman
 * превращается в группу готовности для сессии OpenCode.
 */
final class OcenkaGotovnostiUnitaTest extends TestCase
{
    /**
     * @return array<string, array{bool, ?string, string}>
     */
    public static function kodiSostoyanii(): array
    {
        return [
            'юнит не существует' => [false, null, SostoyanieUnita::GROUP_ARCHIVED],
            'юнит не существует, но код есть' => [false, 'USPESHNO_ZAPUSHEN', SostoyanieUnita::GROUP_ARCHIVED],
            'код не задан' => [true, null, SostoyanieUnita::GROUP_BLOCKED],
            'код пустой' => [true, '', SostoyanieUnita::GROUP_BLOCKED],
            'удалён' => [true, 'UDALEN', SostoyanieUnita::GROUP_ARCHIVED],
            'удалён вручную' => [true, 'UDALEN_VRUCHNUYU', SostoyanieUnita::GROUP_ARCHIVED],
            'успешно запущен' => [true, 'USPESHNO_ZAPUSHEN', SostoyanieUnita::GROUP_READY],
            'ошибка сборки' => [true, 'OSHIBKA_SBORKI', SostoyanieUnita::GROUP_READY],
            'ошибка подготовки' => [true, 'OSHIBKA_PODGOTOVKI', SostoyanieUnita::GROUP_READY],
            'ошибка запуска' => [true, 'OSHIBKA_ZAPUSKA', SostoyanieUnita::GROUP_READY],
            'просто создан' => [true, 'SOZDAN', SostoyanieUnita::GROUP_BLOCKED],
            'сломан' => [true, 'SLOMAN', SostoyanieUnita::GROUP_BLOCKED],
            'собран' => [true, 'USPESHNO_SOBRAN', SostoyanieUnita::GROUP_BLOCKED],
            'подготовлен к запуску' => [true, 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU', SostoyanieUnita::GROUP_BLOCKED],
            'ждёт результата сборки' => [true, 'JDET_RESULTATI_SBORKI', SostoyanieUnita::GROUP_BLOCKED],
            'ждёт результата подготовки' => [true, 'JDET_RESULTATI_PODGOTOVKI', SostoyanieUnita::GROUP_BLOCKED],
            'ждёт результата запуска' => [true, 'JDET_RESULTATI_ZAPUSKA', SostoyanieUnita::GROUP_BLOCKED],
        ];
    }

    #[Test]
    #[DataProvider('kodiSostoyanii')]
    public function gruppa_opredelyaetsya_po_kodu(bool $exists, ?string $code, string $ozhidaemaya): void
    {
        $ocenka = new OcenkaGotovnostiUnita();

        $this->assertSame($ozhidaemaya, $ocenka->ocenit($exists, $code));
    }
}
