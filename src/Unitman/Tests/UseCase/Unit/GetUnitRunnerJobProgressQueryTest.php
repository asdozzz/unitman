<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitProgressZadachiUnita;
use App\Unitman\Business\Model\Runner\RunnerJobProgress;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobState;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UmeetPoluchatProzesiUnitaPoId;
use App\Unitman\Business\ReadModel\Unit\ProzesUnita\ShagZadachiUnita;
use App\Unitman\Business\ReadModel\Unit\ProzesUnita\ZadachaProzesaUnita;
use App\Unitman\Business\UseCase\Unit\GetUnitRunnerJobProgressQuery;
use PHPUnit\Framework\TestCase;

/**
 * Pull-режим прогресса: запрос обязан сначала проверить, что задача
 * принадлежит указанному процессу юнита, и только затем обращаться к раннеру.
 *
 * Источник прогресса выбирается по состоянию задачи в БД: завершённая задача
 * с проставленными шагами отдаётся из БД (переживает истечение TTL в KV),
 * во всех остальных случаях — снимок из Runner.
 *
 * Тест чистый (без БД и контейнера): оба порта — интерфейсы, поведение
 * полностью определяется моками.
 */
final class GetUnitRunnerJobProgressQueryTest extends TestCase
{
    private const PROZES_ID = 'prozes-1';
    private const ZADACHA_ID = 'job-1';

    public function test_vernet_snapsho_ot_runnera(): void
    {
        $expected = new RunnerJobProgress(
            self::ZADACHA_ID,
            [new RunnerJobStep('docker build', 'ok', true, 1730000000)],
            true,
            true,
            1730000100,
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->once())
            ->method('poluchitProgressZadachi')
            ->with(self::ZADACHA_ID)
            ->willReturn($expected);

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort(), $runnerService);

        $this->assertSame($expected, $query->handle($this->command()));
    }

    public function test_pri_otsutstvii_snapshota_vozvrashchaet_pustoi_progress(): void
    {
        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->once())
            ->method('poluchitProgressZadachi')
            ->with(self::ZADACHA_ID)
            ->willReturn(null);

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort(), $runnerService);

        $progress = $query->handle($this->command());

        // Задача только запущена: UI должен уметь отрисовать «выполняется»
        // и дождаться первого push-события, а не получить ошибку.
        $this->assertSame(self::ZADACHA_ID, $progress->jobId);
        $this->assertSame([], $progress->steps);
        $this->assertFalse($progress->finished);
        $this->assertFalse($progress->success);
        $this->assertSame(0, $progress->updatedAt);

        $this->assertSame([
            'jobId' => self::ZADACHA_ID,
            'steps' => [],
            'finished' => false,
            'success' => false,
            'updatedAt' => 0,
        ], $progress->toArray());
    }

    public function test_privadlezhnost_zadachi_proveryaetsya_do_obrashcheniya_k_runneru(): void
    {
        $port = $this->createMock(UmeetPoluchatProzesiUnitaPoId::class);
        $port->expects($this->once())
            ->method('poluchitZadachuPoId')
            ->with(self::PROZES_ID, self::ZADACHA_ID)
            ->willReturn(new ZadachaProzesaUnita(self::ZADACHA_ID, 'sborka', 'pending'));

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->once())
            ->method('poluchitProgressZadachi')
            ->willReturn(null);

        $query = new GetUnitRunnerJobProgressQuery($port, $runnerService);
        $query->handle($this->command());
    }

    public function test_chuzhaya_zadacha_ne_privodit_k_zaprosu_v_runner(): void
    {
        $port = $this->createMock(UmeetPoluchatProzesiUnitaPoId::class);
        $port->expects($this->once())
            ->method('poluchitZadachuPoId')
            ->willThrowException(new \DomainException('unit.runner_job_not_found'));

        $runnerService = $this->createMock(RunnerService::class);
        // Прогресс чужого jobId не должен даже запрашиваться.
        $runnerService->expects($this->never())->method('poluchitProgressZadachi');

        $query = new GetUnitRunnerJobProgressQuery($port, $runnerService);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.runner_job_not_found');

        $query->handle($this->command());
    }

    public function test_snapsho_otdaetsya_v_formate_push_kanala(): void
    {
        // Один и тот же payload для push (Centrifugo) и pull: фронтенд
        // обрабатывает оба источника одной логикой.
        $progress = new RunnerJobProgress(
            self::ZADACHA_ID,
            [new RunnerJobStep('docker build', 'ok', false, 1730000000)],
            false,
            false,
            1730000050,
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->method('poluchitProgressZadachi')->willReturn($progress);

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort(), $runnerService);

        $this->assertSame([
            'jobId' => self::ZADACHA_ID,
            'steps' => [
                ['command' => 'docker build', 'response' => 'ok', 'success' => false, 'unixtime' => 1730000000],
            ],
            'finished' => false,
            'success' => false,
            'updatedAt' => 1730000050,
        ], $query->handle($this->command())->toArray());
    }

    public function test_zavershennaya_zadacha_s_shagami_beretsya_iz_bd(): void
    {
        $zadacha = new ZadachaProzesaUnita(
            self::ZADACHA_ID,
            'sborka',
            RunnerJobState::SUCCESS->value,
            [new ShagZadachiUnita('docker build', 'ok', true, 1730000000)],
        );

        $runnerService = $this->createMock(RunnerService::class);
        // Снимок в KV мог истечь — для завершённой задачи он не нужен.
        $runnerService->expects($this->never())->method('poluchitProgressZadachi');

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort($zadacha), $runnerService);

        $this->assertSame([
            'jobId' => self::ZADACHA_ID,
            'steps' => [
                ['command' => 'docker build', 'response' => 'ok', 'success' => true, 'unixtime' => 1730000000],
            ],
            'finished' => true,
            'success' => true,
            'updatedAt' => 1730000000,
        ], $query->handle($this->command())->toArray());
    }

    public function test_oshibka_zadachi_iz_bd_daet_success_false(): void
    {
        $zadacha = new ZadachaProzesaUnita(
            self::ZADACHA_ID,
            'sborka',
            RunnerJobState::ERROR->value,
            [new ShagZadachiUnita('docker build', 'failed', false, 1730000200)],
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->never())->method('poluchitProgressZadachi');

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort($zadacha), $runnerService);

        $progress = $query->handle($this->command());

        $this->assertTrue($progress->finished);
        $this->assertFalse($progress->success);
        $this->assertSame(1730000200, $progress->updatedAt);
    }

    public function test_otmenennaya_zadacha_iz_bd_schitaetsya_zavershennoy(): void
    {
        $zadacha = new ZadachaProzesaUnita(
            self::ZADACHA_ID,
            'sborka',
            RunnerJobState::CANCLED->value,
            [new ShagZadachiUnita('CANCEL', 'cancelled by user', false, 1730000300)],
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->never())->method('poluchitProgressZadachi');

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort($zadacha), $runnerService);

        $progress = $query->handle($this->command());

        $this->assertTrue($progress->finished);
        $this->assertFalse($progress->success);
    }

    public function test_zavershennaya_zadacha_bez_shagov_beretsya_iz_kv(): void
    {
        // Терминальное состояние без шагов в БД — шаги ещё не проставлены,
        // поэтому источником остаётся снимок раннера.
        $zadacha = new ZadachaProzesaUnita(self::ZADACHA_ID, 'sborka', RunnerJobState::SUCCESS->value);

        $expected = new RunnerJobProgress(
            self::ZADACHA_ID,
            [new RunnerJobStep('docker build', 'ok', true, 1730000000)],
            true,
            true,
            1730000100,
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->once())
            ->method('poluchitProgressZadachi')
            ->with(self::ZADACHA_ID)
            ->willReturn($expected);

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort($zadacha), $runnerService);

        $this->assertSame($expected, $query->handle($this->command()));
    }

    public function test_nezavershennaya_zadacha_s_shagami_beretsya_iz_kv(): void
    {
        // Пока задача не завершена, БД не является источником прогресса,
        // даже если какие-то шаги уже успели записаться.
        $zadacha = new ZadachaProzesaUnita(
            self::ZADACHA_ID,
            'sborka',
            RunnerJobState::PENDING->value,
            [new ShagZadachiUnita('docker build', 'ok', true, 1730000000)],
        );

        $expected = new RunnerJobProgress(
            self::ZADACHA_ID,
            [new RunnerJobStep('docker build', 'ok', true, 1730000000)],
            false,
            false,
            1730000050,
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->once())
            ->method('poluchitProgressZadachi')
            ->with(self::ZADACHA_ID)
            ->willReturn($expected);

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort($zadacha), $runnerService);

        $this->assertSame($expected, $query->handle($this->command()));
    }

    public function test_updated_at_beretsya_iz_poslednego_nepustogo_shaga(): void
    {
        $zadacha = new ZadachaProzesaUnita(
            self::ZADACHA_ID,
            'sborka',
            RunnerJobState::SUCCESS->value,
            [
                new ShagZadachiUnita('docker build', 'ok', true, 1730000000),
                new ShagZadachiUnita('docker push', 'ok', true, 1730000400),
                new ShagZadachiUnita('cleanup', 'skipped', true, 0),
            ],
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->never())->method('poluchitProgressZadachi');

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort($zadacha), $runnerService);

        $this->assertSame(1730000400, $query->handle($this->command())->updatedAt);
    }

    public function test_iz_bd_otdaetsya_v_formate_push_kanala(): void
    {
        $zadacha = new ZadachaProzesaUnita(
            self::ZADACHA_ID,
            'sborka',
            RunnerJobState::SUCCESS->value,
            [new ShagZadachiUnita('docker build', 'ok', true, 1730000000)],
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->never())->method('poluchitProgressZadachi');

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort($zadacha), $runnerService);

        $this->assertSame([
            'jobId' => self::ZADACHA_ID,
            'steps' => [
                ['command' => 'docker build', 'response' => 'ok', 'success' => true, 'unixtime' => 1730000000],
            ],
            'finished' => true,
            'success' => true,
            'updatedAt' => 1730000000,
        ], $query->handle($this->command())->toArray());
    }

    public function test_iz_bd_peredaet_vremya_nachala_shaga(): void
    {
        // Время начала необязательно: шаг с ним получает дополнительный ключ,
        // шаг без него — прежний набор из 4 ключей.
        $zadacha = new ZadachaProzesaUnita(
            self::ZADACHA_ID,
            'sborka',
            RunnerJobState::SUCCESS->value,
            [
                new ShagZadachiUnita('docker build', 'ok', true, 1730000000, 1729999975),
                new ShagZadachiUnita('docker push', 'ok', true, 1730000400),
            ],
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->never())->method('poluchitProgressZadachi');

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort($zadacha), $runnerService);

        $this->assertSame([
            'jobId' => self::ZADACHA_ID,
            'steps' => [
                [
                    'command' => 'docker build',
                    'response' => 'ok',
                    'success' => true,
                    'unixtime' => 1730000000,
                    'unixtimeStart' => 1729999975,
                ],
                ['command' => 'docker push', 'response' => 'ok', 'success' => true, 'unixtime' => 1730000400],
            ],
            'finished' => true,
            'success' => true,
            'updatedAt' => 1730000400,
        ], $query->handle($this->command())->toArray());
    }

    public function test_sostoyanie_v_nizhnem_registre_tozhe_schitaetsya_terminatelnym(): void
    {
        // Проекция пишет состояние в верхнем регистре, но read-модель может
        // прийти и в нижнем (исторические записи, тестовые данные).
        $zadacha = new ZadachaProzesaUnita(
            self::ZADACHA_ID,
            'sborka',
            'success',
            [new ShagZadachiUnita('docker build', 'ok', true, 1730000000)],
        );

        $runnerService = $this->createMock(RunnerService::class);
        $runnerService->expects($this->never())->method('poluchitProgressZadachi');

        $query = new GetUnitRunnerJobProgressQuery($this->zadachaPort($zadacha), $runnerService);

        $progress = $query->handle($this->command());

        $this->assertTrue($progress->finished);
        $this->assertTrue($progress->success);
    }

    private function command(): PoluchitProgressZadachiUnita
    {
        return new PoluchitProgressZadachiUnita(self::PROZES_ID, self::ZADACHA_ID);
    }

    private function zadachaPort(?ZadachaProzesaUnita $zadacha = null): UmeetPoluchatProzesiUnitaPoId
    {
        $zadacha ??= new ZadachaProzesaUnita(self::ZADACHA_ID, 'sborka', 'pending');

        $port = $this->createMock(UmeetPoluchatProzesiUnitaPoId::class);
        $port->method('poluchitZadachuPoId')
            ->willReturn($zadacha);

        return $port;
    }
}
