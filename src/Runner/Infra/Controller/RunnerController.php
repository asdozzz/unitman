<?php

declare(strict_types=1);

namespace App\Runner\Infra\Controller;

use App\Runner\Business\Command\SaveStepsCommand;
use App\Runner\Business\Model\RunnerProgress;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Infra\Repository\SqlRunnerStateRepository;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Runner\Infra\Service\RunnerProgressPublisher;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

    #[OA\Post(
        path: '/api/runner/getDefaultRunnerActive',
        summary: 'Признак активности раннера по умолчанию',
        tags: ['Runner'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Состояние раннера',
                content: new OA\JsonContent(
                    required: ['active'],
                    properties: [new OA\Property(property: 'active', type: 'boolean')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/getDefaultRunnerActive', methods: ['POST'])]
    function getDefaultRunnerActive(SqlRunnerStateRepository $runnerStateRepository): JsonResponse
    {
        $runnerState = $runnerStateRepository->getDefaultRunnerState();
        return new JsonResponse(['active' => $runnerState->isActive()]);
    }

    #[OA\Get(
        path: '/api/runner/test',
        summary: 'Диагностическое состояние раннера',
        tags: ['Runner'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Текущее состояние раннера по умолчанию',
                content: new OA\JsonContent(ref: '#/components/schemas/RunnerState')
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/test', methods: ['GET'])]
    function test(SqlRunnerStateRepository $runnerStateRepository): JsonResponse
    {
        $runners = $runnerStateRepository->getDefaultRunnerState();
        return new JsonResponse($runners);
    }

    #[OA\Post(
        path: '/api/runner/saveSteps',
        summary: 'Сохранить шаги выполнения задачи',
        description: 'Вызывается Golang-раннером, публичный эндпоинт без JWT. '
            . '`responseId` содержит токен прогресса (workflowId/jobId), '
            . '`stepsContent` — полный снапшот шагов. '
            . 'При настроенном RUNNER_CALLBACK_SECRET обязателен заголовок '
            . 'X-Runner-Signature: hex(HMAC-SHA256(responseId . stepsContent, secret)).',
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
            new OA\Response(response: 403, description: 'Некорректная подпись запроса'),
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
        if (!$signatureChecker->estPodpisValidna(
            $command->responseId,
            $command->stepsContent,
            $request->headers->get(RunnerCallbackSignatureChecker::HEADER_NAME),
        )) {
            throw new AccessDeniedHttpException('runner.invalid_signature');
        }

        if (!$progressStorage->estLiToken($command->responseId)) {
            throw new BadRequestHttpException('runner.unknown_progress_token');
        }

        if (strlen($command->stepsContent) > self::MAX_STEPS_CONTENT_BYTES) {
            throw new BadRequestHttpException('runner.steps_too_large');
        }

        // Декодирование — до любых записей: битый JSON не должен оставлять
        // мусор в KV и не должен перезаписывать предыдущий валидный снапшот.
        $steps = $this->decodeSteps($command->stepsContent);

        $progressStorage->zapisatSyrieShagi($command->responseId, $command->stepsContent);

        $progress = new RunnerProgress(
            $command->responseId,
            $steps,
            $command->finished,
            $command->success,
            time(),
        );

        $progressStorage->zapisat($progress);
        $progressPublisher->publish($progress);

        return new JsonResponse(true);
    }

    /**
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

        /** @var list<array<array-key, mixed>> $steps */
        $steps = array_values(array_filter($decoded, 'is_array'));

        return array_map($this->normalizeStep(...), $steps);
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
     * @param array<array-key, mixed> $step
     * @return array<array-key, mixed>
     */
    private function normalizeStep(array $step): array
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
            return $step;
        }

        // Числовая строка допустима: часть JSON-энкодеров отдаёт время строкой.
        if (!is_int($rawStart) && !(is_string($rawStart) && ctype_digit($rawStart))) {
            return $step;
        }

        $start = (int) $rawStart;

        if ($start < 0) {
            return $step;
        }

        $finish = $step['Unixtime'] ?? $step['unixtime'] ?? null;

        if (is_string($finish) && ctype_digit($finish)) {
            $finish = (int) $finish;
        }

        // Начало позже окончания — заведомо битые данные.
        if (is_int($finish) && $start > $finish) {
            return $step;
        }

        $step['UnixtimeStart'] = $start;

        return $step;
    }
}
