<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Service;

use App\Runner\Business\Model\RunnerProgress;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Infra\Service\RunnerProgressPublisher;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use PHPUnit\Framework\TestCase;

/**
 * Публикация прогресса троттлится (не чаще одного раза в 250 мс на токен),
 * но финальный снапшот уходит всегда: по `finished: true` UI закрывает панель.
 * Недоступность Centrifugo не должна ронять приём шагов от раннера.
 *
 * Время публикации хранится в порту, поэтому тесты подкладывают нужное значение
 * заранее — реальные вызовы microtime() не нужны, тест детерминирован.
 */
final class RunnerProgressPublisherTest extends TestCase
{
    private const TOKEN = 'job-1';

    private const STEPS = [
        ['Command' => 'docker build', 'Response' => 'ok', 'Success' => true, 'Unixtime' => 1730000000],
    ];

    public function test_pervaya_publikaciya_ushla_v_kanal_tokens(): void
    {
        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->expects($this->once())
            ->method('publish')
            ->with(
                $this->callback(static fn(array $payload): bool => $payload['jobId'] === self::TOKEN
                    && $payload['finished'] === false
                    && $payload['success'] === false
                    && $payload['steps'] === self::STEPS
                    && $payload['updatedAt'] === 1730000000),
                RunnerProgressPublisher::CHANNEL_PREFIX . self::TOKEN,
            );

        $publisher = new RunnerProgressPublisher($centrifugo, new InMemoryProgressStorage());

        $this->assertTrue($publisher->publish($this->progress()));
    }

    public function test_publikaciya_v_predelah_okna_podavlyaetsya(): void
    {
        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->expects($this->never())->method('publish');

        $storage = new InMemoryProgressStorage();
        // 100 мс назад — внутри окна 250 мс.
        $storage->zapomnitVremyaPublikacii(self::TOKEN, $this->nowMs() - 100);

        $publisher = new RunnerProgressPublisher($centrifugo, $storage);

        $this->assertFalse($publisher->publish($this->progress()));
    }

    public function test_publikaciya_posle_okna_prohodit(): void
    {
        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->expects($this->once())->method('publish');

        $storage = new InMemoryProgressStorage();
        // 300 мс назад — окно уже вышло.
        $storage->zapomnitVremyaPublikacii(self::TOKEN, $this->nowMs() - 300);

        $publisher = new RunnerProgressPublisher($centrifugo, $storage);

        $this->assertTrue($publisher->publish($this->progress()));
    }

    public function test_finalnyi_snapshoт_publikuetsya_minuya_trottling(): void
    {
        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->expects($this->once())
            ->method('publish')
            ->with(
                $this->callback(static fn(array $payload): bool => $payload['finished'] === true
                    && $payload['success'] === true),
                RunnerProgressPublisher::CHANNEL_PREFIX . self::TOKEN,
            );

        $storage = new InMemoryProgressStorage();
        // Публикация была только что — обычный снапшот был бы подавлен.
        $storage->zapomnitVremyaPublikacii(self::TOKEN, $this->nowMs());

        $publisher = new RunnerProgressPublisher($centrifugo, $storage);

        $this->assertTrue($publisher->publish($this->progress(finished: true, success: true)));
    }

    public function test_oshibka_centrifugo_ne_probivaet_narugu(): void
    {
        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->expects($this->once())
            ->method('publish')
            ->willThrowException(new \RuntimeException('centrifugo unavailable'));

        $publisher = new RunnerProgressPublisher($centrifugo, new InMemoryProgressStorage());

        // Приём шагов от раннера не должен падать из-за недоступного Centrifugo:
        // pull-endpoint всё равно отдаст снапшот из KV.
        $this->assertFalse($publisher->publish($this->progress()));
    }

    public function test_trottling_vedetsya_otdelno_dlya_kazhdogo_tokena(): void
    {
        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->expects($this->once())->method('publish');

        $storage = new InMemoryProgressStorage();
        // Второй токен уже публиковался недавно, первый — нет.
        $storage->zapomnitVremyaPublikacii('job-2', $this->nowMs());

        $publisher = new RunnerProgressPublisher($centrifugo, $storage);

        $this->assertTrue($publisher->publish($this->progress('job-1')));
        $this->assertFalse($publisher->publish($this->progress('job-2')));
    }

    public function test_vremya_publikacii_zapominaetsya_do_otpravki(): void
    {
        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->method('publish')->willThrowException(new \RuntimeException('boom'));

        $storage = new InMemoryProgressStorage();
        $publisher = new RunnerProgressPublisher($centrifugo, $storage);

        $publisher->publish($this->progress());

        // Маркер ставится до вызова Centrifugo: при флаппинге сети повторы
        // троттлятся так же, как и успешные публикации.
        $this->assertNotNull($storage->poluchitVremyaPublikacii(self::TOKEN));
    }

    private function progress(
        string $token = self::TOKEN,
        bool $finished = false,
        bool $success = false,
    ): RunnerProgress {
        return new RunnerProgress($token, self::STEPS, $finished, $success, 1730000000);
    }

    private function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}

/**
 * Хранилище только для троттлинга: реальный KV подменяется массивом в памяти.
 */
final class InMemoryProgressStorage implements UmeetHranitProgressZadachi
{
    /** @var array<string, int> */
    private array $publishedAt = [];

    public function zapisat(RunnerProgress $progress): void
    {
    }

    public function zapisatSyrieShagi(string $token, string $stepsContent): void
    {
    }

    public function zaregistrirovat(string $token): void
    {
    }

    public function estLiToken(string $token): bool
    {
        return true;
    }

    public function zapomnitVremyaPublikacii(string $token, int $unixtimeMs): void
    {
        $this->publishedAt[$token] = $unixtimeMs;
    }

    public function poluchitVremyaPublikacii(string $token): ?int
    {
        return $this->publishedAt[$token] ?? null;
    }
}
