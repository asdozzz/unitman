<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Controller;

use App\Runner\Business\Command\UvedomlenieOSostoyaniiKonteynera;
use App\Runner\Business\Command\ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\UmeetObnovlyatSostoyanieKonteyneraUnita;
use App\Runner\Business\UseCase\ZaregistrirovatUvedomleniyaOSostoyaniiKonteyneraUseCase;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Controller\RunnerController;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * `POST /api/runner/unitStateNotifications` — публичный эндпоинт без JWT.
 *
 * Use case получает порт `UmeetObnovlyatSostoyanieKonteyneraUnita` (в проде —
 * `UnitmanAdapter`). В тесте порт подменяется мок-объектом, а use case
 * создаётся напрямую по контракту конструктора: это позволяет детерминированно
 * проверить маппинг уведомления на вызов `obnovit()` без сквозной цепочки
 * Runner → Unitman → PostgreSQL.
 *
 * Очистка полей расхождения при `mismatch = false` выполняется в
 * `SpisokUnitovRepository` (Unitman), поэтому здесь проверяется именно то,
 * что порт получает `mismatch = false` для `eventType = resolved`.
 */
final class RunnerControllerUnitStateTest extends TestCase
{
    private const RUNNER_ID = 'runner-1';
    private const SECRET = 'runner-master-key';

    public function test_mismatch_peredayot_polya_v_port(): void
    {
        $port = $this->createMock(UmeetObnovlyatSostoyanieKonteyneraUnita::class);
        $port->expects($this->once())
            ->method('obnovit')
            ->with(
                'unit-1',
                true,
                'running',
                'exited',
                1730000000,
            );

        $command = $this->command(new UvedomlenieOSostoyaniiKonteynera(
            unitId: 'unit-1',
            projectId: 'project-1',
            unitName: 'unit-name',
            projectName: 'project-name',
            expectedState: 'running',
            actualState: 'exited',
            eventType: 'mismatch',
            detectedAt: 1730000000,
        ));

        $response = $this->send($command, $this->body($command), $port);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('true', $response->getContent());
    }

    public function test_resolved_ochishchaet_priznak_rashozhdeniya(): void
    {
        $port = $this->createMock(UmeetObnovlyatSostoyanieKonteyneraUnita::class);
        $port->expects($this->once())
            ->method('obnovit')
            ->with(
                $this->equalTo('unit-1'),
                $this->isFalse(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
            );

        // Состояния совпадают, поэтому mismatch=false однозначен при любой
        // трактовке: по eventType либо по равенству ожидаемого и фактического.
        $command = $this->command(new UvedomlenieOSostoyaniiKonteynera(
            unitId: 'unit-1',
            projectId: 'project-1',
            unitName: 'unit-name',
            projectName: 'project-name',
            expectedState: 'running',
            actualState: 'running',
            eventType: 'resolved',
            detectedAt: 1730000001,
        ));

        $response = $this->send($command, $this->body($command), $port);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('true', $response->getContent());
    }

    public function test_neizvestnyi_unit_ne_lomaet_zapros(): void
    {
        // Для неизвестного unitId Unitman-порт вернёт false (affected = 0);
        // раннеру всё равно отвечаем 200/true, чтобы он не зацикливался.
        $port = $this->createMock(UmeetObnovlyatSostoyanieKonteyneraUnita::class);
        $port->method('obnovit');

        $command = $this->command(new UvedomlenieOSostoyaniiKonteynera(
            unitId: 'unknown-unit',
            projectId: 'project-1',
            unitName: 'unknown',
            projectName: 'project-name',
            expectedState: 'running',
            actualState: 'exited',
            eventType: 'mismatch',
            detectedAt: 1730000002,
        ));

        $response = $this->send($command, $this->body($command), $port);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('true', $response->getContent());
    }

    public function test_nevalidnaya_podpis_otklonyaetsya(): void
    {
        $port = $this->createMock(UmeetObnovlyatSostoyanieKonteyneraUnita::class);
        $port->expects($this->never())->method('obnovit');

        $command = $this->command(new UvedomlenieOSostoyaniiKonteynera(
            unitId: 'unit-1',
            projectId: 'project-1',
            unitName: 'unit-name',
            projectName: 'project-name',
            expectedState: 'running',
            actualState: 'exited',
            eventType: 'mismatch',
            detectedAt: 1730000003,
        ));

        $request = Request::create(
            '/api/runner/unitStateNotifications',
            'POST',
            [],
            [],
            [],
            [],
            $this->body($command),
        );
        $request->headers->set(RunnerCallbackSignatureChecker::RUNNER_ID_HEADER, self::RUNNER_ID);
        $request->headers->set(RunnerCallbackSignatureChecker::HEADER_NAME, 'deadbeef');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException::class);
        $this->expectExceptionMessage('runner.invalid_signature');

        (new RunnerController())->unitStateNotifications(
            $command,
            $request,
            $this->checker(),
            new ZaregistrirovatUvedomleniyaOSostoyaniiKonteyneraUseCase($port),
        );
    }

    private function command(UvedomlenieOSostoyaniiKonteynera $notification): ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera
    {
        return new ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera(self::RUNNER_ID, [$notification]);
    }

    private function send(
        ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera $command,
        string $body,
        UmeetObnovlyatSostoyanieKonteyneraUnita $port,
    ): JsonResponse {
        $request = Request::create('/api/runner/unitStateNotifications', 'POST', [], [], [], [], $body);
        $request->headers->set(RunnerCallbackSignatureChecker::RUNNER_ID_HEADER, self::RUNNER_ID);
        $request->headers->set(
            RunnerCallbackSignatureChecker::HEADER_NAME,
            hash_hmac('sha256', $body, hash_hmac('sha256', self::RUNNER_ID, self::SECRET)),
        );

        return (new RunnerController())->unitStateNotifications(
            $command,
            $request,
            $this->checker(),
            new ZaregistrirovatUvedomleniyaOSostoyaniiKonteyneraUseCase($port),
        );
    }

    private function checker(): RunnerCallbackSignatureChecker
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save(RunnerState::sozdatRunner(self::RUNNER_ID, self::RUNNER_ID));

        return new RunnerCallbackSignatureChecker($repository, new RunnerSecretGenerator(self::SECRET));
    }

    private function body(ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera $command): string
    {
        $notification = $command->notifications[0];

        return json_encode([
            'runnerId' => $command->runnerId,
            'notifications' => [
                [
                    'unitId' => $notification->unitId,
                    'projectId' => $notification->projectId,
                    'unitName' => $notification->unitName,
                    'projectName' => $notification->projectName,
                    'expectedState' => $notification->expectedState,
                    'actualState' => $notification->actualState,
                    'eventType' => $notification->eventType,
                    'detectedAt' => $notification->detectedAt,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
