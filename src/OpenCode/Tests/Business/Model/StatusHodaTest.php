<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\StatusHoda;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Статус хода — единственный источник правды о том, можно ли ещё ждать
 * результат и нужно ли принимать ответ пользователя.
 */
final class StatusHodaTest extends TestCase
{
    /**
     * @return array<string, array{0: StatusHoda, 1: bool, 2: bool}>
     */
    public static function statusyProvider(): array
    {
        return [
            'running: ход идёт, ответа не ждём' => [StatusHoda::RUNNING, false, false],
            'awaiting_input: ход завершён, но требует ответа' => [StatusHoda::AWAITING_INPUT, true, true],
            'answered: ход завершён окончательно' => [StatusHoda::ANSWERED, true, false],
            'failed: ход завершён ошибкой' => [StatusHoda::FAILED, true, false],
        ];
    }

    #[Test]
    #[DataProvider('statusyProvider')]
    public function zavershennost_i_ozhidanie_otveta(StatusHoda $status, bool $zavershen, bool $zhdetOtveta): void
    {
        $this->assertSame($zavershen, $status->zavershen());
        $this->assertSame($zhdetOtveta, $status->trebuetOtveta());
    }

    #[Test]
    public function po_stroke_sozdaet_izvestnyi_status(): void
    {
        foreach (StatusHoda::cases() as $status) {
            $this->assertSame($status, StatusHoda::poStrokoi($status->value));
        }
    }

    #[Test]
    public function po_stroke_na_neizvestnom_znachenii_brosaet_domennuyu_oshibku(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.turn.invalid_status');

        StatusHoda::poStrokoi('IN_PROGRESS');
    }
}
