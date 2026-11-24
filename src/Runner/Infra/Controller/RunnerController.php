<?php

declare(strict_types=1);

namespace App\Runner\Infra\Controller;

use App\Runner\Business\Command\SaveStepsCommand;
use App\Runner\Business\Command\ZaregistrirovatHealthRunnera;
use App\Runner\Business\Command\ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera;
use App\Runner\Business\Model\RunnerProgress;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Business\UseCase\PoluchitSpisokRannerovQuery;
use App\Runner\Business\UseCase\PoluchitUgrozyRannerovQuery;
use App\Runner\Business\UseCase\RegisterRunner\RunnerRegistrationFailedException;
use App\Runner\Business\UseCase\RegisterRunner\RunnerRegistrationInvalidPayloadException;
use App\Runner\Business\UseCase\RegisterRunnerUseCase;
use App\Runner\Business\UseCase\ZaregistrirovatHealthRunneraUseCase;
use App\Runner\Business\UseCase\ZaregistrirovatUvedomleniyaOSostoyaniiKonteyneraUseCase;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Runner\Infra\Service\RunnerProgressPublisher;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/runner')]
final class RunnerController extends AbstractController
{
    /**
     * Максимальный размер снапшота шагов.
     *
     * Шаги приходят ПОЛНЫМ снапшотом на каждый вызов, поэтому ограничение
     * защищает KV и Centrifugo от раздувания (например, при зацикливании
     * вывода команды на стороне раннера).
     */
    private const MAX_STEPS_CONTENT_BYTES = 2560 * 1024;

    /**
     * Максимальный размер тела health-push.
     */
    private const MAX_HEALTH_CONTENT_BYTES = 2 * 1024 * 1024;

    /**
     * Максимальный размер тела пакета уведомлений о состоянии контейнеров.
     */
    private const MAX_UNIT_STATE_CONTENT_BYTES = 1 * 1024 * 1024;

    /**
     * Максимальное количество шагов в снапшоте.
     *
     * Общий лимит байт не мешает раннеру прислать десятки тысяч крошечных
     * шагов: тогда основные затраты уходят не на JSON, а на сами PHP-массивы
     * и повторную сериализацию для Centrifugo.
     */
    private const MAX_STEPS = 5000;

    /**
     * Максимальный размер поля `Response` одного шага.
     *
     * Вывод одной команды может быть произвольно большим: ограничиваем его,
     * чтобы один шаг не занимал основную часть лимита снапшота.
     */
    private const MAX_STEP_RESPONSE_BYTES = 65536;

    #[OA\Post(
        path: '/api/runner/register',
        summary: 'Регистрация Go-раннера (multi-runner, v2)',
        description: 'Публичный эндпоинт без JWT и без bootstrap-подписи. '
            . 'Принимает `{hostname, version}`. Возвращает runnerId (UUID v4), '
            . 'taskQueue (строго равен runnerId), детерминированный secret '
            . '(64 hex, отдаётся один раз и не хранится), registryHost и карту портов.',
        tags: ['Runner'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'hostname', type: 'string'),
                    new OA\Property(property: 'version', type: 'string'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Раннер зарегистрирован',
                content: new OA\JsonContent(
                    required: ['runnerId', 'taskQueue', 'secret', 'registryHost', 'ports'],
                    properties: [
                        new OA\Property(property: 'runnerId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'taskQueue', type: 'string'),
                        new OA\Property(property: 'secret', type: 'string', description: '64 hex, не сохраняется'),
                        new OA\Property(property: 'registryHost', type: 'string'),
                        new OA\Property(
                            property: 'ports',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'api', type: 'integer'),
                                new OA\Property(property: 'temporal', type: 'integer'),
                                new OA\Property(property: 'registry', type: 'integer'),
                            ]
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'runner.registration.invalid_payload'),
            new OA\Response(response: 500, description: 'runner.registration.failed'),
        ],
        security: []
    )]
    #[Route('/register', methods: ['POST'])]
    function register(Request $request, RegisterRunnerUseCase $useCase): JsonResponse
    {
        try {
            $result = $useCase->handle($request->getContent());
        } catch (RunnerRegistrationInvalidPayloadException $exception) {
            return $this->registrationError($exception->getMessage(), 400);
        } catch (RunnerRegistrationFailedException $exception) {
            return $this->registrationError($exception->getMessage(), 500);
        }

        // Контракт v2: плоский ответ 201 Created.
        return new JsonResponse([
            'runnerId' => $result->runnerId,
            'taskQueue' => $result->taskQueue,
            'secret' => $result->secret,
            'registryHost' => $result->registryHost,
            'ports' => $result->ports,
        ], 201);
    }

    /**
     * Ошибка регистрации в общем формате приложения (`FailEnvelope`).
     * `code` продублирован отдельным полем: клиенту нужен машинный код,
     * а `message` проходит через человекочитаемый перевод.
     */
    private function registrationError(string $code, int $status): JsonResponse
    {
        return new JsonResponse(
            \App\Utils\Model\Reponse\Response::fail(['message' => $code, 'code' => $code]),
            $status,
        );
    }

    #[OA\Get(
        path: '/api/runner/list',
        summary: 'Список раннеров с состоянием ресурсов (администратор)',
        description: 'Карточки всех раннеров, отсортированные от худшего уровня угрозы. '
            . 'Доступно только администратору.',
        tags: ['Runner'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Карточки раннеров',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/RannerKartaReadModel')
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав (не администратор)'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/list', methods: ['GET'])]
    function list(PoluchitSpisokRannerovQuery $query): JsonResponse
    {
        return $this->json(\App\Utils\Model\Reponse\Response::success($query->handle()));
    }

    #[OA\Get(
        path: '/api/runner/threats',
        summary: 'Сводка угроз по раннерам для шапки сайта',
        description: 'Доступно всем авторизованным пользователям. Содержит только уровни '
            . 'и идентификаторы проблемных раннеров, без деталей ресурсов.',
        tags: ['Runner'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Сводка угроз',
                content: new OA\JsonContent(ref: '#/components/schemas/UgrozyRannerovReadModel')
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/threats', methods: ['GET'])]
    function threats(PoluchitUgrozyRannerovQuery $query): JsonResponse
    {
        return $this->json(\App\Utils\Model\Reponse\Response::success($query->handle()));
    }

    #[OA\Post(
        path: '/api/runner/saveSteps',
        summary: 'Сохранить шаги выполнения задачи',
        description: 'Вызывается Golang-раннером, публичный эндпоинт без JWT. '
            . '`responseId` содержит токен прогресса (workflowId/jobId), '
            . '`stepsContent` — полный снапшот шагов. '
            . 'Обязательны X-Runner-Id и X-Runner-Signature: '
            . 'hex(HMAC-SHA256(responseId . stepsContent, secret)).',
        tags: ['Runner'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SaveStepsCommand')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Признак успешного сохранения',
                content: new OA\JsonContent(type: 'boolean', example: true)
            ),
            new OA\Response(response: 400, description: 'Неизвестный токен прогресса либо превышен размер снапшота'),
            new OA\Response(response: 401, description: 'Некорректная подпись запроса'),
            new OA\Response(response: 404, description: 'runner.not_found'),
        ],
        security: []
    )]
    #[Route('/saveSteps', methods: ['POST'])]
    function saveSteps(
        SaveStepsCommand $command,
        Request $request,
        RunnerCallbackSignatureChecker $signatureChecker,
        UmeetHranitProgressZadachi $progressStorage,
        RunnerProgressPublisher $progressPublisher,
    ): JsonResponse {
        $runnerId = $request->headers->get(RunnerCallbackSignatureChecker::RUNNER_ID_HEADER);

        try {
            $signatureValid = $signatureChecker->estPodpisValidna(
                $runnerId,
                $command->responseId,
                $command->stepsContent,
                $request->headers->get(RunnerCallbackSignatureChecker::HEADER_NAME),
            );
        } catch (\DomainException) {
            throw new NotFoundHttpException('runner.not_found');
        }

        if (!$signatureValid) {
            throw new UnauthorizedHttpException('RunnerSignature', 'runner.invalid_signature');
        }

        if (!$progressStorage->estLiToken($command->responseId)) {
            throw new BadRequestHttpException('runner.unknown_progress_token');
        }

        if (strlen($command->stepsContent) > self::MAX_STEPS_CONTENT_BYTES) {
            throw new BadRequestHttpException('runner.steps_too_large');
        }

        // Декодирование и нормализация — до любых записей: битый JSON
        // не должен оставлять мусор в KV и перезаписывать предыдущий снапшот.
        $steps = $this->decodeSteps($command->stepsContent);

        $progressStorage->zapisatSyrieShagi($command->responseId, $command->stepsContent);

        // Сериализуем нормализованные шаги ровно один раз: готовый JSON
        // уходит в KV как есть, без повторного кодирования массива.
        $stepsJson = json_encode($steps, JSON_THROW_ON_ERROR);
        $unixtime = time();

        $progressStorage->zapisatSyrieProgress(
            $command->responseId,
            $stepsJson,
            $command->finished,
            $command->success,
            $unixtime,
        );

        // Centrifugo публикуется из массива: сериализацию выполняет
        // Centrifugo-клиент, ручное кодирование здесь только добавило бы копию.
        $progress = new RunnerProgress(
            $command->responseId,
            $steps,
            $command->finished,
            $command->success,
            $unixtime,
        );

        $progressPublisher->publish($progress);

        return new JsonResponse(true);
    }

    #[OA\Post(
        path: '/api/runner/health',
        summary: 'Health-push раннера',
        description: 'Вызывается Golang-раннером, публичный эндпоинт без JWT. '
            . 'Обновляет состояние раннера и статистику контейнеров. '
            . 'Обязательны X-Runner-Id и X-Runner-Signature: '
            . 'hex(HMAC-SHA256(тело_запроса, secret)).',
        tags: ['Runner'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ZaregistrirovatHealthRunnera')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Признак успешной обработки',
                content: new OA\JsonContent(type: 'boolean', example: true)
            ),
            new OA\Response(response: 400, description: 'Пустой runnerId либо превышен размер тела'),
            new OA\Response(response: 401, description: 'Некорректная подпись запроса'),
            new OA\Response(response: 404, description: 'runner.not_found'),
        ],
        security: []
    )]
    #[Route('/health', methods: ['POST'])]
    function health(
        ZaregistrirovatHealthRunnera $command,
        Request $request,
        RunnerCallbackSignatureChecker $signatureChecker,
        ZaregistrirovatHealthRunneraUseCase $useCase,
    ): JsonResponse {
        $body = $request->getContent();

        if (strlen($body) > self::MAX_HEALTH_CONTENT_BYTES) {
            throw new BadRequestHttpException('runner.health_payload_too_large');
        }

        if (trim($command->runnerId) === '') {
            throw new BadRequestHttpException('runner.health_runner_id_empty');
        }

        $runnerId = $request->headers->get(RunnerCallbackSignatureChecker::RUNNER_ID_HEADER);

        try {
            $signatureValid = $signatureChecker->estPodpisTelaValidna(
                $runnerId,
                $body,
                $request->headers->get(RunnerCallbackSignatureChecker::HEADER_NAME),
            );
        } catch (\DomainException) {
            throw new NotFoundHttpException('runner.not_found');
        }

        // Подпись подтверждает личность раннера: обновлять состояние чужого
        // runnerId нельзя.
        if (!$signatureValid || $runnerId !== $command->runnerId) {
            throw new UnauthorizedHttpException('RunnerSignature', 'runner.invalid_signature');
        }

        $useCase->handle($command);

        return new JsonResponse(true);
    }

    #[OA\Post(
        path: '/api/runner/unitStateNotifications',
        summary: 'Уведомления раннера о состоянии контейнеров юнитов',
        description: 'Вызывается Golang-раннером, публичный эндпоинт без JWT. '
            . 'Обязательны X-Runner-Id и X-Runner-Signature: '
            . 'hex(HMAC-SHA256(тело_запроса, secret)).',
        tags: ['Runner'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Признак успешной обработки',
                content: new OA\JsonContent(type: 'boolean', example: true)
            ),
            new OA\Response(response: 400, description: 'Пустой список уведомлений либо превышен размер тела'),
            new OA\Response(response: 401, description: 'Некорректная подпись запроса'),
            new OA\Response(response: 404, description: 'runner.not_found'),
        ],
        security: []
    )]
    #[Route('/unitStateNotifications', methods: ['POST'])]
    function unitStateNotifications(
        ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera $command,
        Request $request,
        RunnerCallbackSignatureChecker $signatureChecker,
        ZaregistrirovatUvedomleniyaOSostoyaniiKonteyneraUseCase $useCase,
    ): JsonResponse {
        $body = $request->getContent();

        if (strlen($body) > self::MAX_UNIT_STATE_CONTENT_BYTES) {
            throw new BadRequestHttpException('runner.unit_state_payload_too_large');
        }

        $runnerId = $request->headers->get(RunnerCallbackSignatureChecker::RUNNER_ID_HEADER);

        try {
            $signatureValid = $signatureChecker->estPodpisTelaValidna(
                $runnerId,
                $body,
                $request->headers->get(RunnerCallbackSignatureChecker::HEADER_NAME),
            );
        } catch (\DomainException) {
            throw new NotFoundHttpException('runner.not_found');
        }

        if (!$signatureValid || $runnerId !== $command->runnerId) {
            throw new UnauthorizedHttpException('RunnerSignature', 'runner.invalid_signature');
        }

        if ($command->notifications === []) {
            throw new BadRequestHttpException('runner.unit_state_payload_empty');
        }

        $useCase->handle($command);

        return new JsonResponse(true);
    }

    /**
     * Декодирует снапшот ровно один раз и нормализует шаги одним проходом.
     *
     * В отличие от `array_filter` + `array_map` промежуточные копии массива
     * не создаются: не-массивы отбрасываются на месте, а элементы шагов
     * нормализуются по ссылке.
     *
     * @return list<array<array-key, mixed>>
     */
    private function decodeSteps(string $stepsContent): array
    {
        if ($stepsContent === '') {
            return [];
        }

        try {
            $decoded = json_decode($stepsContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('runner.steps_malformed');
        }

        if (!is_array($decoded)) {
            throw new BadRequestHttpException('runner.steps_malformed');
        }

        $steps = [];

        foreach ($decoded as &$step) {
            if (!is_array($step)) {
                continue;
            }

            $this->normalizeStep($step);
            $this->assertStepResponseSize($step);

            $steps[] = $step;

            if (count($steps) > self::MAX_STEPS) {
                throw new BadRequestHttpException('runner.steps_too_many');
            }
        }

        unset($step, $decoded);

        return $steps;
    }

    /**
     * Проверяет размер поля `Response` шага.
     *
     * Нестроковые значения не проверяются: контракт ожидает строку вывода
     * команды, остальное просто не является «тяжёлым» полем шага.
     *
     * @param array<array-key, mixed> $step
     */
    private function assertStepResponseSize(array $step): void
    {
        $response = $step['Response'] ?? null;

        if (is_string($response) && strlen($response) > self::MAX_STEP_RESPONSE_BYTES) {
            throw new BadRequestHttpException('runner.step_too_large');
        }
    }

    /**
     * Приводит необязательное время начала шага к каноничному ключу `UnixtimeStart`.
     *
     * Раннер может сериализовать структуру как `UnixtimeStart`, так и в нижнем
     * регистре, поэтому оба варианта читаются и заменяются одним каноничным.
     *
     * Обратная совместимость:
     * - ключа нет — снапшот не меняется (в KV уходит ровно то, что прислали);
     * - значение невалидно (не число, отрицательное, начало позже окончания) —
     *   поле отбрасывается, а не ломает приём прогресса целиком.
     *
     * Шаг принимается по ссылке: нормализация выполняется на месте, без копии
     * массива шага на каждый вызов.
     *
     * @param array<array-key, mixed> $step
     */
    private function normalizeStep(array &$step): void
    {
        $rawStart = null;
        $found = false;

        foreach (['UnixtimeStart', 'unixtimeStart'] as $key) {
            if (!array_key_exists($key, $step)) {
                continue;
            }

            if (!$found) {
                $rawStart = $step[$key];
                $found = true;
            }

            unset($step[$key]);
        }

        if (!$found) {
            return;
        }

        // Числовая строка допустима: часть JSON-энкодеров отдаёт время строкой.
        if (!is_int($rawStart) && !(is_string($rawStart) && ctype_digit($rawStart))) {
            return;
        }

        $start = (int) $rawStart;

        if ($start < 0) {
            return;
        }

        $finish = $step['Unixtime'] ?? $step['unixtime'] ?? null;

        if (is_string($finish) && ctype_digit($finish)) {
            $finish = (int) $finish;
        }

        // Начало позже окончания — заведомо битые данные.
        if (is_int($finish) && $start > $finish) {
            return;
        }

        $step['UnixtimeStart'] = $start;
    }
}
