<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Controller;

use App\Account\Api\AccountApi;
use App\AiChat\Infra\Command\AiChatRequest;
use App\AiChat\Infra\Service\OpenCodeChatProxy;
use App\AiChat\Infra\Service\Transport\ProviderSessionTransportUnknownException;
use App\AiChat\Infra\Service\UpstreamInvalidResponseException;
use App\AiChat\Infra\Service\UpstreamUnavailableException;
use App\Utils\Model\Reponse\Response as ApiResponse;
use App\Utils\Service\ExceptionTranslator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * POST /api/ai/chat — проксирование одного шага AI-чата в OpenCode.
 *
 * Ключ пользователя расшифровывается в AccountApi и передаётся в прокси
 * только как аргумент; ни контроллер, ни прокси не хранят его в свойствах.
 * Upstream-ответ ретранслируется как есть (статус и JSON-тело).
 */
final class AiChatController
{
    public function __construct(
        private readonly AccountApi $accountApi,
        private readonly OpenCodeChatProxy $proxy,
        private readonly ExceptionTranslator $exceptionTranslator,
        private readonly string $openCodeApiUrl,
    ) {
    }

    #[OA\Post(
        path: '/api/ai/chat',
        summary: 'Один шаг AI-чата через OpenAI-совместимый API OpenCode',
        description: 'Принимает историю сообщений и набор инструментов, '
            . 'расшифровывает API-ключ текущего пользователя и ретранслирует '
            . 'ответ upstream. Ключ не покидает бэкенд.',
        tags: ['AI'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['messages'],
                properties: [
                    new OA\Property(
                        property: 'messages',
                        description: 'История сообщений в формате OpenAI chat completions',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['role'],
                            properties: [
                                new OA\Property(
                                    property: 'role',
                                    type: 'string',
                                    enum: ['system', 'user', 'assistant', 'tool'],
                                ),
                                new OA\Property(property: 'content', type: 'string'),
                                new OA\Property(
                                    property: 'tool_calls',
                                    description: 'Вызовы инструментов ассистентом',
                                    type: 'array',
                                    items: new OA\Items(type: 'object'),
                                ),
                            ],
                        ),
                    ),
                    new OA\Property(
                        property: 'tools',
                        description: 'Активные инструменты OpenAI tools',
                        type: 'array',
                        items: new OA\Items(type: 'object'),
                    ),
                    new OA\Property(property: 'model', type: 'string', nullable: true),
                    new OA\Property(property: 'credentialId', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'sessionId',
                        description: 'Идентификатор provider-сессии; передаётся так, как требует политика провайдера (header/body/query/none)',
                        type: 'string',
                        nullable: true,
                        maxLength: 128,
                        pattern: '^[A-Za-z0-9._:-]{1,128}$',
                    ),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ответ OpenAI-совместимого API (choices, usage и т.д.)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'choices',
                            type: 'array',
                            items: new OA\Items(type: 'object'),
                        ),
                        new OA\Property(property: 'usage', type: 'object'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(
                response: 422,
                description: 'Некорректный payload, нет реквизитов или upstream не настроен',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
            new OA\Response(
                response: 502,
                description: 'Ошибка upstream OpenCode',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
        ],
    )]
    #[Route('/api/ai/chat', methods: ['POST'])]
    public function __invoke(
        #[CurrentUser] ?UserInterface $user,
        Request $request,
    ): JsonResponse {
        if ($user === null) {
            return $this->oshibka(Response::HTTP_UNAUTHORIZED, 'account.not_authenticated');
        }

        try {
            $chatRequest = AiChatRequest::fromArray($request->toArray());
        } catch (\JsonException|\InvalidArgumentException) {
            return $this->oshibka(Response::HTTP_UNPROCESSABLE_ENTITY, 'ai.chat.invalid_payload');
        }

        try {
            $credential = $this->accountApi->poluchitRekvizityOpenCodeDlyaTekuschegoPolzovatelya(
                $chatRequest->credentialId,
                $chatRequest->model,
            );
            $policy = $this->accountApi
                ->poluchitPolitikuSessiiProvaydera($credential->provider)
                ->vPolitiku();
        } catch (\DomainException $exception) {
            return $this->izDomainOshibki($exception);
        }

        $credentialUrl = trim($credential->url);
        $baseUrl = $credentialUrl !== '' ? $credentialUrl : trim($this->openCodeApiUrl);

        if ($baseUrl === '') {
            return $this->oshibka(Response::HTTP_UNPROCESSABLE_ENTITY, 'ai.chat.upstream_not_configured');
        }

        try {
            $result = $this->proxy->otpravit(
                $baseUrl,
                $credential->apiKey,
                $credential->model,
                $chatRequest->messages,
                $chatRequest->tools,
                $credential->provider,
                $policy,
                $chatRequest->sessionId,
            );
        } catch (ProviderSessionTransportUnknownException) {
            return $this->oshibka(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'ai.chat.provider_session_transport_unknown',
            );
        } catch (UpstreamUnavailableException) {
            return $this->oshibka(Response::HTTP_BAD_GATEWAY, 'ai.chat.upstream_unavailable');
        } catch (UpstreamInvalidResponseException) {
            return $this->oshibka(Response::HTTP_BAD_GATEWAY, 'ai.chat.upstream_invalid_response');
        }

        return new JsonResponse($result['payload'], $result['status']);
    }

    private function izDomainOshibki(\DomainException $exception): JsonResponse
    {
        return match ($exception->getMessage()) {
            'account.opencode_credential.not_found' => $this->oshibka(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'ai.chat.credential_required',
            ),
            'account.opencode_credential.model_not_available' => $this->oshibka(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'account.opencode_credential.model_not_available',
            ),
            'account.opencode_credential.empty_provider',
            'account.opencode_credential.unknown_provider' => $this->oshibka(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'ai.chat.provider_not_supported',
            ),
            'account.not_authenticated' => $this->oshibka(
                Response::HTTP_UNAUTHORIZED,
                'account.not_authenticated',
            ),
            default => throw $exception,
        };
    }

    /**
     * Единый формат прикладной ошибки: FailEnvelope с переведённым текстом
     * и стабильным ключом в поле `code`.
     */
    private function oshibka(int $status, string $key): JsonResponse
    {
        return new JsonResponse(
            ApiResponse::fail([
                'message' => $this->exceptionTranslator->translate($key),
                'code' => $key,
            ]),
            $status,
        );
    }
}
