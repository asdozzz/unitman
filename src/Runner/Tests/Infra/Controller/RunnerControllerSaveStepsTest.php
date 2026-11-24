<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Controller;

use App\Runner\Business\Command\SaveStepsCommand;
use App\Runner\Business\Model\RunnerProgress;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Controller\RunnerController;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Runner\Infra\Service\RunnerProgressPublisher;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/runner/saveSteps` — публичный эндпоинт (без JWT), поэтому порядок
 * и набор проверок здесь принципиальны: подпись → регистрация токена → размер →
 * корректность JSON, и только затем запись в KV и публикация.
 *
 * Контроллер создаётся напрямую: `saveSteps` не использует контейнер
 * (`new JsonResponse(true)` вместо `$this->json()`), поэтому ядро Symfony
 * поднимать не нужно — тест остаётся быстрым и детерминированным.
 */
final class RunnerControllerSaveStepsTest extends TestCase
{
    private const TOKEN = 'job-777';
    private const RUNNER_ID = 'runner-1';
    // Мастер-ключ: секрет раннера = hmac_sha256(RUNNER_ID, SECRET).
    private const SECRET = 'runner-secret';

    private const STEPS = [
        ['Command' => 'docker build', 'Response' => 'ok', 'Success' => true, 'Unixtime' => 1730000000],
    ];

    /** Должно совпадать с RunnerController::MAX_STEPS_CONTENT_BYTES. */
    private const MAX_STEPS_CONTENT_BYTES = 2560 * 1024;

    /** Должно совпадать с RunnerController::MAX_STEPS. */
    private const MAX_STEPS = 5000;

    /** Должно совпадать с RunnerController::MAX_STEP_RESPONSE_BYTES. */
    private const MAX_STEP_RESPONSE_BYTES = 65536;

    public function test_validnyi_zapros_zapisyvaet_progress_i_publikuet(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $centrifugo = $this->createMock(CentrifugoInterface::class);
        $centrifugo->expects($this->once())
            ->method('publish')
            ->with(
                $this->callback(static fn(array $payload): bool => $payload['jobId'] === self::TOKEN
                    && $payload['finished'] === true
                    && $payload['success'] === true),
                RunnerProgressPublisher::CHANNEL_PREFIX . self::TOKEN,
            );

        $command = $this->command(self::TOKEN, self::STEPS, true, true);

        $response = $this->saveSteps($command, $this->signedRequest($command), self::SECRET, $storage, $centrifugo);

        $this->assertSame('true', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());

        // Прогресс пишется через метод с готовым stepsJson — массив шагов
        // не кодируется повторно; старый zapisat() не вызывается.
        $this->assertSame(1, $storage->syrieProgressCalls);
        $this->assertSame(0, $storage->zapisatCalls);

        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertTrue($progress->finished);
        $this->assertTrue($progress->success);
        $this->assertSame(self::STEPS, $progress->steps);

        // Сырые шаги сохраняются под исходным ключом для обратной совместимости
        // с чтением шагов по ResponseId из результата активити.
        $this->assertSame($command->stepsContent, $storage->syrieShagi[self::TOKEN] ?? null);
    }

    public function test_payload_progressa_v_kv_dekodiruetsya_v_tot_zhe_runner_progress(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $command = $this->command(self::TOKEN, self::STEPS, true, true);

        $this->saveSteps($command, new Request(), '', $storage);

        $payloadRaw = $storage->progressPayloads[self::TOKEN] ?? null;
        $this->assertNotNull($payloadRaw);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($payloadRaw, true, 512, JSON_THROW_ON_ERROR);

        // Форма payload осталась прежней и совпадает с RunnerProgress::toArray().
        $this->assertSame(
            ['token', 'steps', 'finished', 'success', 'unixtime'],
            array_keys($payload),
        );
        $this->assertSame(self::TOKEN, $payload['token']);
        $this->assertSame(self::STEPS, $payload['steps']);
        $this->assertTrue($payload['finished']);
        $this->assertTrue($payload['success']);
        $this->assertIsInt($payload['unixtime']);

        // Обратное чтение из «KV» даёт эквивалентный снапшот.
        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertSame(self::STEPS, $progress->steps);
        $this->assertSame($payload['unixtime'], $progress->unixtime);
    }

    public function test_syrie_shagi_sohranyayutsya_bez_normalizacii(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $content = json_encode([
            [
                'Command' => 'docker build',
                'Response' => 'ok',
                'Success' => true,
                'Unixtime' => 1730000000,
                'unixtimeStart' => '1729999975',
            ],
        ], JSON_THROW_ON_ERROR);

        $this->saveSteps(new SaveStepsCommand(self::TOKEN, $content), new Request(), '', $storage);

        // Сырые шаги — ровно присланный раннером JSON, без нормализации.
        $this->assertSame($content, $storage->syrieShagi[self::TOKEN] ?? null);

        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertSame(1729999975, $progress->steps[0]['UnixtimeStart']);
    }

    public function test_limit_kolichestva_shagov_rovno_na_granice_dopuskaetsya(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $steps = array_fill(0, self::MAX_STEPS, ['Command' => 'step']);

        $response = $this->saveSteps($this->command(self::TOKEN, $steps), new Request(), '', $storage);

        $this->assertSame('true', $response->getContent());

        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertCount(self::MAX_STEPS, $progress->steps);
    }

    public function test_prevyshenie_kolichestva_shagov_otklonyaetsya(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $steps = array_fill(0, self::MAX_STEPS + 1, ['Command' => 'step']);

        try {
            $this->saveSteps($this->command(self::TOKEN, $steps), new Request(), '', $storage);
            $this->fail('Ожидалось BadRequestHttpException');
        } catch (BadRequestHttpException $e) {
            $this->assertSame('runner.steps_too_many', $e->getMessage());
            // Отклонение происходит до любых записей в KV.
            $this->assertArrayNotHasKey(self::TOKEN, $storage->syrieShagi);
            $this->assertSame(0, $storage->syrieProgressCalls);
            $this->assertNull($storage->poluchit(self::TOKEN));
        }
    }

    public function test_slishkom_bolshoi_response_otklonyaetsya(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $content = json_encode([
            [
                'Command' => 'docker logs',
                'Response' => str_repeat('x', self::MAX_STEP_RESPONSE_BYTES + 1),
                'Success' => true,
                'Unixtime' => 1730000000,
            ],
        ], JSON_THROW_ON_ERROR);

        try {
            $this->saveSteps(new SaveStepsCommand(self::TOKEN, $content), new Request(), '', $storage);
            $this->fail('Ожидалось BadRequestHttpException');
        } catch (BadRequestHttpException $e) {
            $this->assertSame('runner.step_too_large', $e->getMessage());
            $this->assertArrayNotHasKey(self::TOKEN, $storage->syrieShagi);
            $this->assertSame(0, $storage->syrieProgressCalls);
            $this->assertNull($storage->poluchit(self::TOKEN));
        }
    }

    public function test_response_rovno_na_granice_dopuskaetsya(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $content = json_encode([
            [
                'Command' => 'docker logs',
                'Response' => str_repeat('x', self::MAX_STEP_RESPONSE_BYTES),
                'Success' => true,
                'Unixtime' => 1730000000,
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->saveSteps(new SaveStepsCommand(self::TOKEN, $content), new Request(), '', $storage);

        $this->assertSame('true', $response->getContent());
        $this->assertNotNull($storage->poluchit(self::TOKEN));
    }

    public function test_nevalidnaya_podpis_otklonyaetsya_bez_zapisi(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $command = $this->command(self::TOKEN, self::STEPS);

        try {
            $this->saveSteps($command, $this->requestWithSignature('deadbeef'), self::SECRET, $storage);
            $this->fail('Ожидалось UnauthorizedHttpException');
        } catch (UnauthorizedHttpException) {
            // Ничего не записано: подпись проверяется до любых обращений к KV.
            $this->assertNull($storage->poluchit(self::TOKEN));
            $this->assertArrayNotHasKey(self::TOKEN, $storage->syrieShagi);
        }
    }

    public function test_otsutstvie_podpisi_otklonyaetsya(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('runner.invalid_signature');

        $this->saveSteps(
            $this->command(self::TOKEN, self::STEPS),
            $this->requestWithRunnerIdOnly(),
            self::SECRET,
            $storage,
        );
    }

    public function test_neizvestnyi_runner_daet_404(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $request = Request::create(
            '/api/runner/saveSteps',
            'POST',
            [],
            [],
            [],
            ['HTTP_X_RUNNER_ID' => 'unknown-runner', 'HTTP_X_RUNNER_SIGNATURE' => 'deadbeef'],
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('runner.not_found');

        $this->saveSteps($this->command(self::TOKEN, self::STEPS), $request, self::SECRET, $storage);

        // Подпись не проходит — ничего не записано.
        $this->assertNull($storage->poluchit(self::TOKEN));
    }

    public function test_neizvestnyi_token_otklonyaetsya(): void
    {
        // Токен не регистрировался: приём шагов по чужому/устаревшему jobId запрещён.
        $storage = new InMemorySaveStepsStorage();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('runner.unknown_progress_token');

        $this->saveSteps($this->command(self::TOKEN, self::STEPS), new Request(), '', $storage);
    }

    public function test_prevyshenie_razmera_snapshota_otklonyaetsya(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        // Валидный JSON, но больше лимита.
        $content = '[' . str_repeat(' ', self::MAX_STEPS_CONTENT_BYTES) . ']';
        $this->assertGreaterThan(self::MAX_STEPS_CONTENT_BYTES, strlen($content));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('runner.steps_too_large');

        $this->saveSteps(new SaveStepsCommand(self::TOKEN, $content), new Request(), '', $storage);
    }

    public function test_bityi_json_otklonyaetsya_i_ne_perezapisyvaet_snapshota(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $valid = new RunnerProgress(self::TOKEN, self::STEPS, false, false, 1730000000);
        $storage->zapisat($valid);

        try {
            $this->saveSteps(new SaveStepsCommand(self::TOKEN, '{"broken": '), new Request(), '', $storage);
            $this->fail('Ожидалось BadRequestHttpException');
        } catch (BadRequestHttpException $e) {
            $this->assertSame('runner.steps_malformed', $e->getMessage());
            // Предыдущий валидный снапшот не затёрт мусором.
            $this->assertEquals($valid, $storage->poluchit(self::TOKEN));
        }
    }

    public function test_json_ne_massiv_otklonyaetsya(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('runner.steps_malformed');

        $this->saveSteps(new SaveStepsCommand(self::TOKEN, '"just a string"'), new Request(), '', $storage);
    }

    public function test_pustoi_snapsho_dopuskaetsya_i_daet_pustoi_spisok_shagov(): void
    {
        // Раннер может прислать пустой снапшот в самом начале задачи.
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $response = $this->saveSteps(new SaveStepsCommand(self::TOKEN, ''), new Request(), '', $storage);

        $this->assertSame('true', $response->getContent());

        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertSame([], $progress->steps);
    }

    public function test_ne_massivnye_elementy_shagov_otbrasyvayutsya(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $content = json_encode([self::STEPS[0], 'garbage', 42, null, ['Command' => 'second']], JSON_THROW_ON_ERROR);

        $this->saveSteps(new SaveStepsCommand(self::TOKEN, $content), new Request(), '', $storage);

        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertSame([self::STEPS[0], ['Command' => 'second']], $progress->steps);
    }

    public function test_vremya_nachala_shaga_sohranyaetsya_kak_est_vo_vhodnom_snapshote(): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $steps = [
            [
                'Command' => 'docker build',
                'Response' => 'ok',
                'Success' => true,
                'Unixtime' => 1730000000,
                'UnixtimeStart' => 1729999975,
            ],
        ];

        $this->saveSteps($this->command(self::TOKEN, $steps), new Request(), '', $storage);

        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertSame($steps, $progress->steps);
    }

    public function test_vremya_nachala_v_camel_case_privoditsya_k_kanonichnomu_klyuchu(): void
    {
        // Раннер может сериализовать Go-структуру в нижнем регистре.
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $content = json_encode([
            [
                'Command' => 'docker build',
                'Response' => 'ok',
                'Success' => true,
                'Unixtime' => 1730000000,
                'unixtimeStart' => '1729999975',
            ],
        ], JSON_THROW_ON_ERROR);

        $this->saveSteps(new SaveStepsCommand(self::TOKEN, $content), new Request(), '', $storage);

        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertSame([
            [
                'Command' => 'docker build',
                'Response' => 'ok',
                'Success' => true,
                'Unixtime' => 1730000000,
                'UnixtimeStart' => 1729999975,
            ],
        ], $progress->steps);
    }

    public function test_otsutstvie_vremeni_nachala_ne_dobavlyaet_klyuch_v_snapsho(): void
    {
        // Главное требование обратной совместимости: старый раннер
        // не присылает поле — снапшот остаётся идентичным прежнему.
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $this->saveSteps($this->command(self::TOKEN, self::STEPS), new Request(), '', $storage);

        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertSame(self::STEPS, $progress->steps);
        $this->assertArrayNotHasKey('UnixtimeStart', $progress->steps[0]);
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function nevvalidnoeVremyaNachalaProvider(): iterable
    {
        yield 'не число' => ['not-a-timestamp'];
        yield 'отрицательное' => [-1];
        yield 'null' => [null];
        yield 'позже окончания' => [1730000001];
    }

    #[DataProvider('nevvalidnoeVremyaNachalaProvider')]
    public function test_nevvalidnoe_vremya_nachala_otbrasyvaetsya_bez_oshibki(mixed $znachenie): void
    {
        $storage = new InMemorySaveStepsStorage();
        $storage->zaregistrirovat(self::TOKEN);

        $step = self::STEPS[0];
        $step['UnixtimeStart'] = $znachenie;

        $response = $this->saveSteps(
            new SaveStepsCommand(self::TOKEN, json_encode([$step], JSON_THROW_ON_ERROR)),
            new Request(),
            '',
            $storage,
        );

        $this->assertSame('true', $response->getContent());

        $progress = $storage->poluchit(self::TOKEN);
        $this->assertNotNull($progress);
        $this->assertSame(self::STEPS, $progress->steps);
    }

    /**
     * @param list<array<array-key, mixed>> $steps
     */
    private function command(string $token, array $steps, bool $finished = false, bool $success = false): SaveStepsCommand
    {
        return new SaveStepsCommand($token, json_encode($steps, JSON_THROW_ON_ERROR), $finished, $success);
    }

    private function saveSteps(
        SaveStepsCommand $command,
        Request $request,
        string $secret,
        InMemorySaveStepsStorage $storage,
        ?CentrifugoInterface $centrifugo = null,
    ): \Symfony\Component\HttpFoundation\JsonResponse {
        // В старых тестах пустая строка означала «подпись отключена». В v2
        // такого режима нет: пустая строка = «запрос подписан корректно
        // мастер-ключом SECRET».
        if ($secret === '') {
            $request = $this->signedRequest($command);
            $secret = self::SECRET;
        }

        $repository = new InMemoryRunnerRepository();
        $repository->save(RunnerState::sozdatRunner(self::RUNNER_ID, self::RUNNER_ID));

        $checker = new RunnerCallbackSignatureChecker($repository, new RunnerSecretGenerator($secret));
        $publisher = new RunnerProgressPublisher(
            $centrifugo ?? $this->createMock(CentrifugoInterface::class),
            $storage,
        );

        return (new RunnerController())->saveSteps($command, $request, $checker, $storage, $publisher);
    }

    private function signedRequest(SaveStepsCommand $command): Request
    {
        return $this->requestWithSignature(
            hash_hmac(
                'sha256',
                $command->responseId . $command->stepsContent,
                hash_hmac('sha256', self::RUNNER_ID, self::SECRET),
            ),
        );
    }

    private function requestWithRunnerIdOnly(): Request
    {
        return Request::create(
            '/api/runner/saveSteps',
            'POST',
            [],
            [],
            [],
            ['HTTP_X_RUNNER_ID' => self::RUNNER_ID],
        );
    }

    private function requestWithSignature(string $signature): Request
    {
        return Request::create(
            '/api/runner/saveSteps',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_X_RUNNER_ID' => self::RUNNER_ID,
                'HTTP_X_RUNNER_SIGNATURE' => $signature,
            ],
        );
    }
}

/**
 * Хранилище в памяти: повторяет семантику KV-адаптера (снапшот + регистрация
 * токена + маркер публикации), но без Redis.
 */
final class InMemorySaveStepsStorage implements UmeetHranitProgressZadachi
{
    /** @var array<string, RunnerProgress> */
    private array $progress = [];

    /** @var array<string, string> */
    public array $syrieShagi = [];

    /**
     * Точные payload'ы, записанные через zapisatSyrieProgress().
     *
     * @var array<string, string>
     */
    public array $progressPayloads = [];

    public int $syrieProgressCalls = 0;

    public int $zapisatCalls = 0;

    /** @var array<string, int> */
    private array $registeredTokens = [];

    /** @var array<string, int> */
    private array $publishedAt = [];

    public function zapisat(RunnerProgress $progress): void
    {
        ++$this->zapisatCalls;
        $this->progress[$progress->token] = $progress;
    }

    /**
     * Повторяет адаптер: payload собирается из готового stepsJson,
     * затем декодируется обратно, как это сделал бы читатель KV.
     */
    public function zapisatSyrieProgress(
        string $token,
        string $stepsJson,
        bool $finished,
        bool $success,
        int $unixtime,
    ): void {
        ++$this->syrieProgressCalls;

        $payload = '{"token":' . json_encode($token, JSON_THROW_ON_ERROR)
            . ',"steps":' . $stepsJson
            . ',"finished":' . ($finished ? 'true' : 'false')
            . ',"success":' . ($success ? 'true' : 'false')
            . ',"unixtime":' . $unixtime . '}';

        $this->progressPayloads[$token] = $payload;

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        /** @var list<array<array-key, mixed>> $normalizedSteps */
        $normalizedSteps = array_values($decoded['steps']);

        $this->progress[$token] = new RunnerProgress(
            $token,
            $normalizedSteps,
            (bool) $decoded['finished'],
            (bool) $decoded['success'],
            (int) $decoded['unixtime'],
        );
    }

    public function zapisatSyrieShagi(string $token, string $stepsContent): void
    {
        $this->syrieShagi[$token] = $stepsContent;
    }

    public function zaregistrirovat(string $token): void
    {
        $this->registeredTokens[$token] = time();
    }

    public function estLiToken(string $token): bool
    {
        return isset($this->registeredTokens[$token]);
    }

    public function zapomnitVremyaPublikacii(string $token, int $unixtimeMs): void
    {
        $this->publishedAt[$token] = $unixtimeMs;
    }

    public function poluchitVremyaPublikacii(string $token): ?int
    {
        return $this->publishedAt[$token] ?? null;
    }

    public function poluchit(string $token): ?RunnerProgress
    {
        return $this->progress[$token] ?? null;
    }
}
