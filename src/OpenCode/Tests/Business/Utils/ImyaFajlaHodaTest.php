<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Utils\ImyaFajlaHoda;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Нормализация и тип файла-артефакта: traversal, скрытые файлы, allowlist/denylist.
 */
final class ImyaFajlaHodaTest extends TestCase
{
    private const ALLOWED = 'md,txt,json,yaml,yml,csv,log';

    #[Test]
    #[DataProvider('normalizaciya')]
    public function normalizaciya_privodit_imya_k_bezopasnomu_vidu(string $input, string $expected): void
    {
        self::assertSame($expected, $this->imya()->normalizovat($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizaciya(): iterable
    {
        yield 'обычное имя' => ['plan.md', 'plan.md'];
        yield 'расширение в верхнем регистре' => ['PLAN.MD', 'PLAN.md'];
        yield 'posix traversal' => ['../../etc/plan.md', 'plan.md'];
        yield 'windows traversal' => ['..\\..\\windows\\plan.md', 'plan.md'];
        yield 'пробелы и переводы строк удаляются из имени' => ["plan.md\r\n", 'plan.md'];
        yield 'точка в базовом имени сохраняется' => ['feature.plan.v2.md', 'feature.plan.v2.md'];
        yield 'txt' => ['notes.TXT', 'notes.txt'];
        yield 'log' => ['build.LOG', 'build.log'];
    }

    #[Test]
    #[DataProvider('nedopustimye_imena')]
    public function nedopustimoe_imya_otklonyaetsya(string $name): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.name_is_invalid');

        $this->imya()->normalizovat($name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nedopustimye_imena(): iterable
    {
        yield 'пустое' => [''];
        yield 'точка' => ['.'];
        yield 'две точки' => ['..'];
        yield 'скрытый файл' => ['.env.md'];
        yield 'скрытый без расширения' => ['.hidden'];
        yield 'слишком длинное' => [str_repeat('a', 129) . '.md'];
    }

    #[Test]
    #[DataProvider('zapreshennye_rasshireniya')]
    public function zapreshchennyj_tip_otklonyaetsya(string $name): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.artifact.file_type_forbidden');

        $this->imya()->normalizovat($name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function zapreshennye_rasshireniya(): iterable
    {
        yield 'html' => ['plan.html'];
        yield 'html в верхнем регистре' => ['plan.HTML'];
        yield 'js' => ['plan.js'];
        yield 'php' => ['plan.php'];
        yield 'sh' => ['plan.sh'];
        yield 'exe' => ['plan.exe'];
        yield 'без расширения' => ['plan'];
        yield 'неизвестное расширение' => ['plan.bin'];
    }

    #[Test]
    #[DataProvider('mime')]
    public function contentType_opredelyaetsya_po_rasshireniyu(string $name, string $expected): void
    {
        self::assertSame($expected, $this->imya()->contentType($name));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mime(): iterable
    {
        yield 'md' => ['plan.md', 'text/markdown'];
        yield 'txt' => ['plan.txt', 'text/plain'];
        yield 'log' => ['plan.log', 'text/plain'];
        yield 'json' => ['plan.json', 'application/json'];
        yield 'yaml' => ['plan.yaml', 'application/yaml'];
        yield 'yml' => ['plan.yml', 'application/yaml'];
        yield 'csv' => ['plan.csv', 'text/csv'];
        yield 'неизвестный' => ['plan.bin', 'application/octet-stream'];
    }

    #[Test]
    #[DataProvider('prosmotr')]
    public function preview_razreshen_tolko_dlya_tekstovyh_tipov(string $name, bool $expected): void
    {
        self::assertSame($expected, $this->imya()->podderzhivaetProsmotr($name));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function prosmotr(): iterable
    {
        yield 'md' => ['plan.md', true];
        yield 'txt' => ['plan.txt', true];
        yield 'json' => ['plan.json', true];
        yield 'yaml' => ['plan.yaml', true];
        yield 'yml' => ['plan.yml', true];
        yield 'csv' => ['plan.csv', true];
        yield 'log' => ['plan.log', true];
        yield 'pdf' => ['plan.pdf', false];
    }

    private function imya(): ImyaFajlaHoda
    {
        return new ImyaFajlaHoda(self::ALLOWED);
    }
}
