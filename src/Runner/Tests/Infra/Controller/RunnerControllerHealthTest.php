<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Controller;

use App\Runner\Business\Command\ZaregistrirovatHealthRunnera;
use App\Runner\Business\Port\CanGenerateGuid;
use App\Runner\Business\Port\UmeetObnovlyatStatistikuPoUnitam;
use App\Runner\Business\UseCase\SozdatDefoltniiRunnerUseCase;
use App\Runner\Business\UseCase\ZaregistrirovatHealthRunneraUseCase;
use App\Runner\Business\Utils\RunnerHealthPayloadParser;
use App\Runner\Infra\Adapter\MemoryGuidGenerator;
use App\Runner\Infra\Controller\RunnerController;
use App\Runner\Infra\Repository\SqlRunnerStateRepository;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * `POST /api/runner/health` — публичный эндпоинт без JWT.
 *
 * Порядок проверок в контроллере: размер тела → HMAC-подпись тела → пустой
 * runnerId → use case. Секрет передаётся в тестовый чекер напрямую (тот же
 * `RunnerCallbackSignatureChecker`, что и в проде), поэтому тест не зависит
 * от значения `RUNNER_CALLBACK_SECRET` в окружении.
 *
 * Кейс обновления `runner_state` требует БД: раннер создаётся штатным
 * `SozdatDefoltniiRunnerUseCase`, после health-push состояние читается через
 * `SqlRunnerStateRepository`.
 */
final class RunnerControllerHealthTest extends AbstractTestCaseWithTransactionWrapper
{
    private const SECRET = 'runner-secret';

    /** Должно совпадать с RunnerController::MAX_HEALTH_CONTENT_BYTES. */
    private const MAX_HEALTH_CONTENT_BYTES = 2 * 1024 * 1024;

    private const TASK_QUEUE = 'unitman-runner-queue';

    public function test_validnaya_podpis_obnovlyaet_runner_state(): void
    {
        $runnerId = 'runner-health-1';

        self::$container->set(CanGenerateGuid::class, new MemoryGuidGenerator([$runnerId]));

        /** @var SozdatDefoltniiRunnerUseCase $sozdat */
        $sozdat = self::$container->get(SozdatDefoltniiRunnerUseCase::class);
        $sozdat->handle();

        /** @var SqlRunnerStateRepository $repository */
        $repository = self::$container->get(SqlRunnerStateRepository::class);
        $this->assertSame(0, $repository->getById($runnerId)->getUnixtimeLastHealth());

        $unixtime = time();
        $command = new ZaregistrirovatHealthRunnera(
            runnerId: $runnerId,
            taskQueue: self::TASK_QUEUE,
            active: true,
            unixtime: $unixtime,
        );
        $body = $this->body($runnerId, true, $unixtime);

        $response = $this->health($command, $body, hash_hmac('sha256', $body, self::SECRET));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('true', $response->getContent());

        $this->assertGreaterThan(
            0,
            $repository->getById($runnerId)->getUnixtimeLastHealth(),
            'health-push должен сохранить unixtimeLastHealth в runner_state',
        );
    }

    public function test_nevalidnaya_podpis_otklonyaetsya(): void
    {
        $command = new ZaregistrirovatHealthRunnera('runner-1', self::TASK_QUEUE);
        $body = $this->body('runner-1', true, 1730000000);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('runner.invalid_signature');

        $this->health($command, $body, 'deadbeef');
    }

    public function test_otsutstvie_podpisi_otklonyaetsya_pri_zadannom_sekrete(): void
    {
        $command = new ZaregistrirovatHealthRunnera('runner-1', self::TASK_QUEUE);
        $body = $this->body('runner-1', true, 1730000000);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('runner.invalid_signature');

        $this->health($command, $body, null);
    }

    public function test_prevyshenie_razmera_tela_otklonyaetsya(): void
    {
        $command = new ZaregistrirovatHealthRunnera('runner-1', self::TASK_QUEUE);

        $body = str_repeat('a', self::MAX_HEALTH_CONTENT_BYTES + 1);
        $this->assertGreaterThan(self::MAX_HEALTH_CONTENT_BYTES, strlen($body));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('runner.health_payload_too_large');

        // Размер проверяется до подписи, поэтому заголовок не нужен.
        $this->health($command, $body, null);
    }

    public function test_pustoi_runner_id_otklonyaetsya(): void
    {
        $command = new ZaregistrirovatHealthRunnera('   ', self::TASK_QUEUE);
        $body = $this->body('   ', true, 1730000000);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('runner.health_runner_id_empty');

        $this->health($command, $body, hash_hmac('sha256', $body, self::SECRET));
    }

    private function health(
        ZaregistrirovatHealthRunnera $command,
        string $body,
        ?string $signature,
    ): JsonResponse {
        $request = Request::create('/api/runner/health', 'POST', [], [], [], [], $body);

        if ($signature !== null) {
            $request->headers->set(RunnerCallbackSignatureChecker::HEADER_NAME, $signature);
        }

        return (new RunnerController())->health(
            $command,
            $request,
            new RunnerCallbackSignatureChecker(self::SECRET),
            $this->useCase(),
        );
    }

    /**
     * Use case собирается явно по той же схеме, что и в `Runner/di.php`:
     * сервис в контейнере теста доступен только после пересборки кэша,
     * а нам нужен детерминированный тест без зависимости от состояния кэша.
     *
     * Порт статистики подменяется моком: в health-push без `dockerStats`
     * и `memInfo` он не участвует в обновлении `runner_state`, зато мок
     * исключает из теста сквозную цепочку до `UnitmanApi`.
     */
    private function useCase(): ZaregistrirovatHealthRunneraUseCase
    {
        /** @var SqlRunnerStateRepository $repository */
        $repository = self::$container->get(SqlRunnerStateRepository::class);

        return new ZaregistrirovatHealthRunneraUseCase(
            $repository,
            new RunnerHealthPayloadParser(),
            $this->createMock(UmeetObnovlyatStatistikuPoUnitam::class),
        );
    }

    private function body(string $runnerId, bool $active, int $unixtime): string
    {
        return json_encode([
            'runnerId' => $runnerId,
            'taskQueue' => self::TASK_QUEUE,
            'active' => $active,
            'unixtime' => $unixtime,
        ], JSON_THROW_ON_ERROR);
    }
}
