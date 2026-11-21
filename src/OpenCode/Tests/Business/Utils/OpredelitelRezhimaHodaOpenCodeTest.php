<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Utils\OpredelitelRezhimaHodaOpenCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Матрица решения 15.7-а.
 *
 * RW (readOnly = false) только когда владелец — автор или прикреплённый
 * редактор, редактирование включено и ветка не защищена. Любое нарушение
 * делает ход read-only.
 */
final class OpredelitelRezhimaHodaOpenCodeTest extends TestCase
{
    private const AUTHOR_ID = 'author-1';
    private const EDITOR_ID = 'editor-1';
    private const STRANGER_ID = 'stranger-1';

    /**
     * @return iterable<string, array{string, bool, bool, bool, bool}>
     */
    public static function matrica(): iterable
    {
        yield 'автор, редактирование включено' => [self::AUTHOR_ID, true, false, true, false];
        yield 'редактор, редактирование включено' => [self::EDITOR_ID, true, false, true, false];
        yield 'посторонний, редактирование включено' => [self::STRANGER_ID, true, false, true, true];
        yield 'автор, редактирование выключено' => [self::AUTHOR_ID, false, false, true, true];
        yield 'редактор, редактирование выключено' => [self::EDITOR_ID, false, false, true, true];
        yield 'посторонний, редактирование выключено' => [self::STRANGER_ID, false, false, true, true];
        yield 'автор, защищённая ветка' => [self::AUTHOR_ID, true, true, true, true];
        yield 'редактор, защищённая ветка' => [self::EDITOR_ID, true, true, true, true];
        yield 'посторонний, защищённая ветка' => [self::STRANGER_ID, true, true, true, true];
        yield 'автор, выключено и защищённая' => [self::AUTHOR_ID, false, true, true, true];
        yield 'редактор, выключено и защищённая' => [self::EDITOR_ID, false, true, true, true];
        yield 'посторонний, выключено и защищённая' => [self::STRANGER_ID, false, true, true, true];
    }

    #[Test]
    #[DataProvider('matrica')]
    public function read_only_sootvetstvuet_matricе(
        string $owner,
        bool $editing,
        bool $protectedBranch,
        bool $exists,
        bool $expectedReadOnly,
    ): void {
        $sostoyanie = $this->sostoyanie($editing, $protectedBranch, $exists);

        $opredelitel = new OpredelitelRezhimaHodaOpenCode();

        self::assertSame($expectedReadOnly, $opredelitel->readOnly($sostoyanie, $owner));
        self::assertSame(!$expectedReadOnly, $opredelitel->mozhetRedaktirovat($sostoyanie, $owner));
    }

    #[Test]
    public function udalionnii_unit_read_only(): void
    {
        $opredelitel = new OpredelitelRezhimaHodaOpenCode();

        self::assertTrue($opredelitel->readOnly(
            $this->sostoyanie(true, false, false),
            self::AUTHOR_ID,
        ));
    }

    #[Test]
    public function pustoy_vladelec_ne_mozhet_redaktirovat(): void
    {
        $opredelitel = new OpredelitelRezhimaHodaOpenCode();

        self::assertTrue($opredelitel->readOnly($this->sostoyanie(true, false, true), '  '));
    }

    #[Test]
    public function unit_bez_avtora_dostupen_tolko_redaktoram(): void
    {
        $sostoyanie = new SostoyanieUnita(
            true,
            'USPESHNO_ZAPUSHEN',
            'project-1',
            'unit-1',
            'main',
            SostoyanieUnita::GROUP_READY,
            false,
            null,
            null,
            true,
            false,
            [self::EDITOR_ID],
        );

        $opredelitel = new OpredelitelRezhimaHodaOpenCode();

        self::assertTrue($opredelitel->readOnly($sostoyanie, self::AUTHOR_ID));
        self::assertFalse($opredelitel->readOnly($sostoyanie, self::EDITOR_ID));
    }

    private function sostoyanie(bool $editing, bool $protectedBranch, bool $exists): SostoyanieUnita
    {
        return new SostoyanieUnita(
            $exists,
            'USPESHNO_ZAPUSHEN',
            'project-1',
            'unit-1',
            'main',
            SostoyanieUnita::GROUP_READY,
            false,
            null,
            self::AUTHOR_ID,
            $editing,
            $protectedBranch,
            [self::EDITOR_ID],
        );
    }
}
