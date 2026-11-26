<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Controller;

use App\Runner\Business\Command\ZaregistrirovatHealthRunnera;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\UmeetObnovlyatStatistikuPoUnitam;
use App\Runner\Business\UseCase\ZaregistrirovatHealthRunneraUseCase;
use App\Runner\Business\Utils\RunnerHealthPayloadParser;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Controller\RunnerController;
use App\Runner\Infra\Repository\SqlRunnerStateRepository;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/runner/health` — публичный эндпоинт без JWT.
 *
 * Порядок проверок в контроллере: размер тела → пустой runnerId → per-runner
 * HMAC-подпись (X-Runner-Id + X-Runner-Signature) → use case. Неизвестный
 * X-Runner-Id — 404, неверная подпись — 401.
 */
final class RunnerControllerHealthTest extends AbstractTestCaseWithTransactionWrapper
{
    private const MASTER_KEY = 'runner-master-key';

    /** Должно совпадать с RunnerController::MAX_HEALTH_CONTENT_BYTES. */
    private const MAX_HEALTH_CONTENT_BYTES = 2 * 1024 * 1024;

    private const TASK_QUEUE = 'unitman-runner-queue';

    public function test_validnaya_podpis_obnovlyaet_runner_state(): void
    {
        $runnerId = 'runner-health-1';

        /** @var SqlRunnerStateRepository $repository */
        $repository = self::$container->get(SqlRunnerStateRepository::class);

        // Регистрация раннера в этом кейсе не проверяется: health-push
        // обновляет уже существующее состояние, поэтому создаём его напрямую.
        $repository->save(new RunnerState($runnerId, self::TASK_QUEUE, false));
        $this->assertSame(0, $repository->getById($runnerId)->getUnixtimeLastHealth());

        $unixtime = time();
        $command = new ZaregistrirovatHealthRunnera(
            runnerId: $runnerId,
            taskQueue: self::TASK_QUEUE,
            active: true,
            unixtime: $unixtime,
        );
        $body = $this->body($runnerId, true, $unixtime);

        $response = $this->health($command, $body, $this->sign($runnerId, $body), $runnerId);

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
        $runnerId = 'runner-health-invalid';
        $this->saveRunner($runnerId);

        $command = new ZaregistrirovatHealthRunnera($runnerId, self::TASK_QUEUE);
        $body = $this->body($runnerId, true, 1730000000);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('runner.invalid_signature');

        $this->health($command, $body, 'deadbeef', $runnerId);
    }

    public function test_otsutstvie_podpisi_otklonyaetsya(): void
    {
        $runnerId = 'runner-health-missing-signature';
        $this->saveRunner($runnerId);

        $command = new ZaregistrirovatHealthRunnera($runnerId, self::TASK_QUEUE);
        $body = $this->body($runnerId, true, 1730000000);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('runner.invalid_signature');

        $this->health($command, $body, null, $runnerId);
    }

    public function test_prevyshenie_razmera_tela_otklonyaetsya(): void
    {
        $command = new ZaregistrirovatHealthRunnera('runner-1', self::TASK_QUEUE);

        $body = str_repeat('a', self::MAX_HEALTH_CONTENT_BYTES + 1);
        $this->assertGreaterThan(self::MAX_HEALTH_CONTENT_BYTES, strlen($body));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('runner.health_payload_too_large');

        // Размер проверяется до подписи, поэтому заголовки не нужны.
        $this->health($command, $body, null, 'runner-1');
    }

    public function test_pustoi_runner_id_otklonyaetsya(): void
    {
        $command = new ZaregistrirovatHealthRunnera('   ', self::TASK_QUEUE);
        $body = $this->body('   ', true, 1730000000);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('runner.health_runner_id_empty');

        // Пустой runnerId проверяется до подписи.
        $this->health($command, $body, 'whatever', '   ');
    }

    private function saveRunner(string $runnerId): void
    {
        /** @var SqlRunnerStateRepository $repository */
        $repository = self::$container->get(SqlRunnerStateRepository::class);
        $repository->save(new RunnerState($runnerId, $runnerId, true));
    }

    private function health(
        ZaregistrirovatHealthRunnera $command,
        string $body,
        ?string $signature,
        string $runnerId,
    ): JsonResponse {
        $request = Request::create('/api/runner/health', 'POST', [], [], [], [], $body);
        $request->headers->set(RunnerCallbackSignatureChecker::RUNNER_ID_HEADER, $runnerId);

        if ($signature !== null) {
            $request->headers->set(RunnerCallbackSignatureChecker::HEADER_NAME, $signature);
        }

        /** @var SqlRunnerStateRepository $repository */
        $repository = self::$container->get(SqlRunnerStateRepository::class);

        return (new RunnerController())->health(
            $command,
            $request,
            new RunnerCallbackSignatureChecker($repository, RunnerSecretGenerator::fromMasterKey(self::MASTER_KEY)),
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

    private function sign(string $runnerId, string $body): string
    {
        return hash_hmac('sha256', $body, hash_hmac('sha256', $runnerId, self::MASTER_KEY));
    }
}
