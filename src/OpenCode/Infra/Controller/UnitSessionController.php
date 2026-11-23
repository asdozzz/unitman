<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\UnitSession\ReadModel\UnitSessionReadModel;
use App\OpenCode\Business\UnitSession\UseCase\ArhivirovatSessiyuUnitaUseCase;
use App\OpenCode\Business\UnitSession\UseCase\NachatPromptSessiiUnitaUseCase;
use App\OpenCode\Business\UnitSession\UseCase\OtvetitNaVoprosSessiiUnitaUseCase;
use App\OpenCode\Business\UnitSession\UseCase\PoluchitIstoriyuSessiiUnitaQuery;
use App\OpenCode\Business\UnitSession\UseCase\PoluchitProgressHodaSessiiUnitaQuery;
use App\OpenCode\Business\UnitSession\UseCase\PoluchitResultatHodaSessiiUnitaQuery;
use App\OpenCode\Business\UnitSession\UseCase\PoluchitSessiyuPoUnituQuery;
use App\OpenCode\Business\UnitSession\UseCase\PoluchitSostoyanieSessiiUnitaQuery;
use App\OpenCode\Business\UnitSession\UseCase\PoluchitSpisokSessiyUnitaQuery;
use App\OpenCode\Business\UnitSession\UseCase\SozdatSessiyuUnitaUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Unit-сессии OpenCode.
 *
 * Отдельный контроллер и отдельные маршруты: workspace обслуживается
 * WorkspaceController и не пересекается с этим API.
 */
#[Route('/api/opencode/unit-session')]
final class UnitSessionController extends AbstractController
{
    #[OA\Get(
        path: '/api/opencode/unit-session/spisok',
        summary: 'Список unit-сессий текущего пользователя',
        tags: ['UnitSession'],
        parameters: [
            new OA\Parameter(name: 'onlyActive', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', nullable: true)),
        ],
        responses: [new OA\Response(response: 200, description: 'Список unit-сессий')]
    )]
    #[Route('/spisok', methods: ['GET'])]
    public function spisok(Request $request, PoluchitSpisokSessiyUnitaQuery $query): HttpResponse
    {
        $search = trim($request->query->getString('search', ''));

        return $this->json(Response::success(array_map(
            static fn (UnitSessionReadModel $session): array => $session->toArray(),
            $query->handle($request->query->getBoolean('onlyActive', false), $search === '' ? null : $search),
        )));
    }

    #[OA\Post(
        path: '/api/opencode/unit-session/sozdat',
        summary: 'Создать unit-сессию для одного юнита',
        tags: ['UnitSession'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['credentialId', 'unitId'],
                properties: [
                    new OA\Property(property: 'credentialId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'unitId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'title', type: 'string', nullable: true),
                    new OA\Property(property: 'model', type: 'string', nullable: true, description: 'Модель из набора реквизитов; по умолчанию — дефолтная'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Идентификатор созданной сессии')]
    )]
    #[Route('/sozdat', methods: ['POST'])]
    public function sozdat(Request $request, SozdatSessiyuUnitaUseCase $useCase): HttpResponse
    {
        $data = $request->toArray();

        $id = $useCase->handle(
            (string) ($data['credentialId'] ?? ''),
            is_string($data['title'] ?? null) ? $data['title'] : null,
            (string) ($data['unitId'] ?? ''),
            is_string($data['model'] ?? null) ? $data['model'] : null,
        );

        return $this->json(Response::success(['id' => $id]));
    }

    #[OA\Post(
        path: '/api/opencode/unit-session/sessiyaPoUnitu',
        summary: 'Активная unit-сессия юнита',
        tags: ['UnitSession'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Сессия или null')]
    )]
    #[Route('/sessiyaPoUnitu', methods: ['POST'])]
    public function sessiyaPoUnitu(Request $request, PoluchitSessiyuPoUnituQuery $query): HttpResponse
    {
        $data = $request->toArray();
        $session = $query->handle((string) ($data['unitId'] ?? ''));

        return $this->json(Response::success($session?->toArray()));
    }

    #[OA\Post(
        path: '/api/opencode/unit-session/prompt',
        summary: 'Запустить ход unit-сессии',
        tags: ['UnitSession'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['sessionId', 'text'],
                properties: [
                    new OA\Property(property: 'sessionId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'text', type: 'string'),
                    new OA\Property(property: 'model', type: 'string', nullable: true, description: 'Переопределение модели перед ходом'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Запуск хода')]
    )]
    #[Route('/prompt', methods: ['POST'])]
    public function prompt(Request $request, NachatPromptSessiiUnitaUseCase $useCase): HttpResponse
    {
        $data = $request->toArray();
        $result = $useCase->handle(
            (string) ($data['sessionId'] ?? ''),
            (string) ($data['text'] ?? ''),
            is_string($data['model'] ?? null) ? $data['model'] : null,
        );

        return $this->json(Response::success([
            'id' => $result->turnId,
            'status' => $result->status,
            'progressChannel' => $result->progressChannel,
        ]));
    }

    #[OA\Post(
        path: '/api/opencode/unit-session/otvetit',
        summary: 'Ответить на вопросы предыдущего хода',
        tags: ['UnitSession'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['turnId', 'answers'],
                properties: [
                    new OA\Property(property: 'turnId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'answers', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'model', type: 'string', nullable: true, description: 'Переопределение модели перед ходом'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Запуск хода')]
    )]
    #[Route('/otvetit', methods: ['POST'])]
    public function otvetit(Request $request, OtvetitNaVoprosSessiiUnitaUseCase $useCase): HttpResponse
    {
        $data = $request->toArray();
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];

        $result = $useCase->handle(
            (string) ($data['turnId'] ?? ''),
            $answers,
            is_string($data['model'] ?? null) ? $data['model'] : null,
        );

        return $this->json(Response::success([
            'id' => $result->turnId,
            'status' => $result->status,
            'progressChannel' => $result->progressChannel,
        ]));
    }

    #[OA\Post(
        path: '/api/opencode/unit-session/istoriya',
        summary: 'История ходов unit-сессии',
        tags: ['UnitSession'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'История ходов')]
    )]
    #[Route('/istoriya', methods: ['POST'])]
    public function istoriya(Request $request, PoluchitIstoriyuSessiiUnitaQuery $query): HttpResponse
    {
        $data = $request->toArray();

        return $this->json(Response::success(array_map(
            static fn (HodOpenCodeReadModel $turn): array => $turn->toArray(),
            $query->handle((string) ($data['sessionId'] ?? '')),
        )));
    }

    #[OA\Post(
        path: '/api/opencode/unit-session/sostoyanie',
        summary: 'Состояние unit-сессии',
        tags: ['UnitSession'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Состояние сессии')]
    )]
    #[Route('/sostoyanie', methods: ['POST'])]
    public function sostoyanie(Request $request, PoluchitSostoyanieSessiiUnitaQuery $query): HttpResponse
    {
        $data = $request->toArray();

        return $this->json(Response::success(
            $query->handle((string) ($data['sessionId'] ?? '')),
        ));
    }

    #[OA\Post(
        path: '/api/opencode/unit-session/arhivirovat',
        summary: 'Архивировать unit-сессию',
        tags: ['UnitSession'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Сессия архивирована')]
    )]
    #[Route('/arhivirovat', methods: ['POST'])]
    public function arhivirovat(Request $request, ArhivirovatSessiyuUnitaUseCase $useCase): HttpResponse
    {
        $data = $request->toArray();
        $useCase->handle((string) ($data['sessionId'] ?? ''));

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/opencode/unit-session/poluchitResultatHoda',
        summary: 'Результат хода unit-сессии',
        tags: ['UnitSession'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Состояние хода')]
    )]
    #[Route('/poluchitResultatHoda', methods: ['POST'])]
    public function poluchitResultatHoda(Request $request, PoluchitResultatHodaSessiiUnitaQuery $query): HttpResponse
    {
        $data = $request->toArray();

        return $this->json(Response::success(
            $query->handle((string) ($data['turnId'] ?? ''))->toArray(),
        ));
    }

    #[OA\Post(
        path: '/api/opencode/unit-session/poluchitProgressHoda',
        summary: 'Снапшот прогресса хода unit-сессии',
        tags: ['UnitSession'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Прогресс или null')]
    )]
    #[Route('/poluchitProgressHoda', methods: ['POST'])]
    public function poluchitProgressHoda(Request $request, PoluchitProgressHodaSessiiUnitaQuery $query): HttpResponse
    {
        $data = $request->toArray();

        return $this->json(Response::success(
            $query->handle((string) ($data['turnId'] ?? '')),
        ));
    }
}
