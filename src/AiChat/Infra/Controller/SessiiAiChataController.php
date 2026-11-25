<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Controller;

use App\AiChat\Business\Command\SozdatSessiyuAiChata;
use App\AiChat\Business\Command\VypolnitHodAiChata;
use App\AiChat\Business\Model\FiltrSessiyAiChata;
use App\AiChat\Business\Port\RekvizityDlyaSessiiAiChata;
use App\AiChat\Business\UseCase\ArhivirovatSessiyuAiChataUseCase;
use App\AiChat\Business\UseCase\PoluchitSessiyuAiChataQuery;
use App\AiChat\Business\UseCase\PoluchitSpisokSessiyAiChataQuery;
use App\AiChat\Business\UseCase\SozdatSessiyuAiChataUseCase;
use App\AiChat\Business\UseCase\UdalitSessiyuAiChataUseCase;
use App\AiChat\Business\UseCase\VypolnitHodAiChataUseCase;
use App\AiChat\Infra\Command\VypolnitHodAiChataRequest;
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
 * HTTP-контракт сессий AI-чата.
 *
 * Владелец сессии всегда берётся из аутентифицированного пользователя
 * (`RekvizityDlyaSessiiAiChata::tekuschiiAccountId()` внутри use case) и
 * никогда из тела/query запроса. Чужая сессия неотличима от несуществующей:
 * доменная ошибка `ai.chat.session_not_found` отдаётся с HTTP 404.
 *
 * Ошибки домена переводятся в единый конверт `FailResponse` с полем `data.code`,
 * который читает фронтенд-нормализатор. Новых форматов не вводится.
 */
final class SessiiAiChataController
{
    private const KEY_NOT_AUTHENTICATED = 'account.not_authenticated';
    private const KEY_INVALID_PAYLOAD = 'ai.chat.invalid_payload';
    private const KEY_PROVIDER_NOT_SUPPORTED = 'ai.chat.provider_not_supported';

    public function __construct(
        private readonly SozdatSessiyuAiChataUseCase $createSession,
        private readonly PoluchitSpisokSessiyAiChataQuery $listSessions,
        private readonly PoluchitSessiyuAiChataQuery $getSession,
        private readonly ArhivirovatSessiyuAiChataUseCase $archiveSession,
        private readonly UdalitSessiyuAiChataUseCase $deleteSession,
        private readonly VypolnitHodAiChataUseCase $runTurn,
        private readonly RekvizityDlyaSessiiAiChata $rekvizity,
        private readonly ExceptionTranslator $exceptionTranslator,
    ) {
    }

    #[OA\Get(
        path: '/api/ai/chat/sessions',
        summary: 'Список сессий AI-чата текущего пользователя',
        description: 'Пагинация (`limit`, `offset`), поиск (`search`) по заголовку и первому сообщению, '
            . 'фильтр архивных (`includeArchived`). Возвращаются только сессии с хотя бы одним сообщением; '
            . 'в каждой карточке ровно одно превью первого сообщения.',
        tags: ['AI'],
        parameters: [
            new OA\Parameter(
                name: 'includeArchived',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', default: false),
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', nullable: true),
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50),
            ),
            new OA\Parameter(
                name: 'offset',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0, default: 0),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Страница сессий',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AiChatSessionList'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(
                response: 422,
                description: 'Некорректная пагинация или параметры запроса',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
        ],
    )]
    #[Route('/api/ai/chat/sessions', methods: ['GET'])]
    public function list(
        #[CurrentUser] ?UserInterface $user,
        Request $request,
    ): JsonResponse {
        if ($user === null) {
            return $this->oshibka(Response::HTTP_UNAUTHORIZED, self::KEY_NOT_AUTHENTICATED);
        }

        try {
            $filtr = new FiltrSessiyAiChata(
                $this->boolQuery($request, 'includeArchived', false),
                $this->searchQuery($request),
                $this->intQuery($request, 'limit', 50),
                $this->intQuery($request, 'offset', 0),
            );
        } catch (\InvalidArgumentException) {
            return $this->oshibka(Response::HTTP_UNPROCESSABLE_ENTITY, self::KEY_INVALID_PAYLOAD);
        } catch (\DomainException $exception) {
            return $this->izDomainOshibki($exception);
        }

        try {
            $accountId = $this->rekvizity->tekuschiiAccountId();
            $spisok = $this->listSessions->vypolnit($accountId, $filtr);
        } catch (\DomainException $exception) {
            return $this->izDomainOshibki($exception);
        }

        return new JsonResponse(ApiResponse::success($spisok->vMassiv()));
    }

    #[OA\Post(
        path: '/api/ai/chat/sessions',
        summary: 'Создать сессию AI-чата',
        description: 'Без `credentialId` берётся первый реквизит аккаунта, без `model` — модель по умолчанию. '
            . 'Провайдер обязан поддерживать AI-чат.',
        tags: ['AI'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/AiChatSessionCreateRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Сессия создана',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AiChatSession'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(
                response: 422,
                description: 'Нет реквизитов, некорректный payload или провайдер не поддерживает AI-чат',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
        ],
    )]
    #[Route('/api/ai/chat/sessions', methods: ['POST'])]
    public function create(
        #[CurrentUser] ?UserInterface $user,
        Request $request,
    ): JsonResponse {
        if ($user === null) {
            return $this->oshibka(Response::HTTP_UNAUTHORIZED, self::KEY_NOT_AUTHENTICATED);
        }

        try {
            $payload = $this->jsonPayload($request);
            $komanda = new SozdatSessiyuAiChata(
                $this->optionalString($payload, 'credentialId'),
                $this->optionalString($payload, 'model'),
                $this->optionalString($payload, 'title'),
            );
        } catch (\JsonException|\InvalidArgumentException) {
            return $this->oshibka(Response::HTTP_UNPROCESSABLE_ENTITY, self::KEY_INVALID_PAYLOAD);
        }

        try {
            $session = $this->createSession->vypolnit($komanda);
        } catch (\DomainException $exception) {
            return $this->izDomainOshibki($exception);
        }

        return new JsonResponse(ApiResponse::success($session->vMassiv()), Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/ai/chat/sessions/{id}',
        summary: 'Сессия AI-чата с полной историей',
        description: 'Архивная или истёкшая сессия доступна только для чтения. Чужая сессия — 404.',
        tags: ['AI'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Сессия и история сообщений',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AiChatSessionDetail'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(
                response: 404,
                description: 'Сессия не найдена или принадлежит другому аккаунту',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
        ],
    )]
    #[Route('/api/ai/chat/sessions/{id}', methods: ['GET'])]
    public function show(
        string $id,
        #[CurrentUser] ?UserInterface $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->oshibka(Response::HTTP_UNAUTHORIZED, self::KEY_NOT_AUTHENTICATED);
        }

        try {
            $session = $this->getSession->vypolnit($id);
        } catch (\DomainException $exception) {
            return $this->izDomainOshibki($exception);
        }

        return new JsonResponse(ApiResponse::success($session->vMassiv()));
    }

    #[OA\Post(
        path: '/api/ai/chat/sessions/{id}/archive',
        summary: 'Архивировать сессию AI-чата',
        description: 'Идемпотентно: повторная архивация не меняет причину и время. Чужая сессия — 404.',
        tags: ['AI'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Сессия архивирована',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AiChatSession'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(
                response: 404,
                description: 'Сессия не найдена или принадлежит другому аккаунту',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
        ],
    )]
    #[Route('/api/ai/chat/sessions/{id}/archive', methods: ['POST'])]
    public function archive(
        string $id,
        #[CurrentUser] ?UserInterface $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->oshibka(Response::HTTP_UNAUTHORIZED, self::KEY_NOT_AUTHENTICATED);
        }

        try {
            $session = $this->archiveSession->vypolnit($id);
        } catch (\DomainException $exception) {
            return $this->izDomainOshibki($exception);
        }

        return new JsonResponse(ApiResponse::success($session->vMassiv()));
    }

    #[OA\Delete(
        path: '/api/ai/chat/sessions/{id}',
        summary: 'Удалить архивную сессию AI-чата',
        description: 'Разрешено только для архивной сессии; активную сначала нужно архивировать. '
            . 'Чужая сессия — 404.',
        tags: ['AI'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Сессия удалена',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(
                response: 404,
                description: 'Сессия не найдена или принадлежит другому аккаунту',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
            new OA\Response(
                response: 409,
                description: 'Сессия не архивирована',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
        ],
    )]
    #[Route('/api/ai/chat/sessions/{id}', methods: ['DELETE'])]
    public function remove(
        string $id,
        #[CurrentUser] ?UserInterface $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->oshibka(Response::HTTP_UNAUTHORIZED, self::KEY_NOT_AUTHENTICATED);
        }

        try {
            $this->deleteSession->vypolnit($id);
        } catch (\DomainException $exception) {
            return $this->izDomainOshibki($exception);
        }

        return new JsonResponse(ApiResponse::success(null));
    }

    #[OA\Post(
        path: '/api/ai/chat/sessions/{id}/turns',
        summary: 'Один шаг диалога AI-чата',
        description: 'Ровно одно из `message` или `toolResults`. Архивная/истёкшая сессия — 409; '
            . 'неизвестный провайдер — 422. Чужая сессия — 404.',
        tags: ['AI'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AiChatTurnRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ответ ассистента и метаданные сессии',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AiChatTurnResult'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(
                response: 404,
                description: 'Сессия не найдена или принадлежит другому аккаунту',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
            new OA\Response(
                response: 409,
                description: 'Сессия архивна, истекла или реквизиты удалены',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
            new OA\Response(
                response: 422,
                description: 'Некорректный payload, нет реквизитов или провайдер не поддерживается',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
            new OA\Response(
                response: 502,
                description: 'Ошибка upstream-провайдера',
                content: new OA\JsonContent(ref: '#/components/schemas/FailEnvelope'),
            ),
        ],
    )]
    #[Route('/api/ai/chat/sessions/{id}/turns', methods: ['POST'])]
    public function turn(
        string $id,
        #[CurrentUser] ?UserInterface $user,
        Request $request,
    ): JsonResponse {
        if ($user === null) {
            return $this->oshibka(Response::HTTP_UNAUTHORIZED, self::KEY_NOT_AUTHENTICATED);
        }

        try {
            $payload = $this->jsonPayload($request);
            $zapros = VypolnitHodAiChataRequest::fromArray($payload);
        } catch (\JsonException|\InvalidArgumentException) {
            return $this->oshibka(Response::HTTP_UNPROCESSABLE_ENTITY, self::KEY_INVALID_PAYLOAD);
        }

        $komanda = new VypolnitHodAiChata(
            $id,
            $zapros->message,
            $zapros->toolResults,
            $zapros->tools,
            $zapros->model,
        );

        try {
            $rezultat = $this->runTurn->vypolnit($komanda);
        } catch (UpstreamUnavailableException $exception) {
            return $this->oshibka(Response::HTTP_BAD_GATEWAY, $exception->getMessage());
        } catch (UpstreamInvalidResponseException $exception) {
            return $this->oshibka(Response::HTTP_BAD_GATEWAY, $exception->getMessage());
        } catch (\DomainException $exception) {
            return $this->izDomainOshibki($exception);
        }

        return new JsonResponse(ApiResponse::success($rezultat->vMassiv()));
    }

    private function izDomainOshibki(\DomainException $exception): JsonResponse
    {
        $key = $exception->getMessage();

        return match ($key) {
            self::KEY_NOT_AUTHENTICATED => $this->oshibka(Response::HTTP_UNAUTHORIZED, $key),
            'ai.chat.session_not_found' => $this->oshibka(Response::HTTP_NOT_FOUND, $key),
            'ai.chat.session_archived',
            'ai.chat.session_expired',
            'ai.chat.session_not_archived',
            'ai.chat.credential_deleted',
            'ai.chat.session_message_limit' => $this->oshibka(Response::HTTP_CONFLICT, $key),
            self::KEY_PROVIDER_NOT_SUPPORTED,
            'ai.chat.provider_session_transport_unknown',
            'ai.chat.credential_required',
            self::KEY_INVALID_PAYLOAD,
            'ai.chat.invalid_limit',
            'ai.chat.invalid_offset',
            'account.opencode_credential.model_not_available' => $this->oshibka(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $key,
            ),
            'account.opencode_credential.not_found' => $this->oshibka(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'ai.chat.credential_required',
            ),
            'account.opencode_credential.empty_provider',
            'account.opencode_credential.unknown_provider' => $this->oshibka(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                self::KEY_PROVIDER_NOT_SUPPORTED,
            ),
            default => throw $exception,
        };
    }

    /**
     * Единый формат прикладной ошибки совпадает с legacy-контроллером AI-чата:
     * `FailEnvelope` + переведённый текст и стабильный ключ в `data.code`.
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

    /**
     * @return array<string, mixed>
     *
     * @throws \JsonException
     * @throws \InvalidArgumentException
     */
    private function jsonPayload(Request $request): array
    {
        $content = trim($request->getContent());

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!\is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \InvalidArgumentException
     */
    private function optionalString(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];

        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        return $value;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function boolQuery(Request $request, string $name, bool $default): bool
    {
        $value = $request->query->all()[$name] ?? null;

        if ($value === null) {
            return $default;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        $normalized = strtolower(trim($value));

        if (\in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (\in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
            return false;
        }

        throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function intQuery(Request $request, string $name, int $default): int
    {
        $value = $request->query->all()[$name] ?? null;

        if ($value === null) {
            return $default;
        }

        if (!\is_string($value) || preg_match('/^-?\d+$/', trim($value)) !== 1) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        return (int) trim($value);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function searchQuery(Request $request): ?string
    {
        $value = $request->query->all()['search'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        return $value;
    }
}
