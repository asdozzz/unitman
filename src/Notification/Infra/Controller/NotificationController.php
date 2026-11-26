<?php

declare(strict_types=1);

namespace App\Notification\Infra\Controller;

use App\Notification\Business\Command\IzmenitNastroikuUvedomleniya;
use App\Notification\Business\Command\OtpisatsyaOtPush;
use App\Notification\Business\Command\PodpisatsyaNaPush;
use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\UseCase\IzmenitNastroikuUvedomleniyaUseCase;
use App\Notification\Business\UseCase\OtmetitUvedomlenieProchitannimUseCase;
use App\Notification\Business\UseCase\OtmetitVseUvedomleniyaProchitannimiUseCase;
use App\Notification\Business\UseCase\OtpisatsyaOtPushUseCase;
use App\Notification\Business\UseCase\PodpisatsyaNaPushUseCase;
use App\Notification\Business\UseCase\PoluchitKolichestvoNeprochitannihQuery;
use App\Notification\Business\UseCase\PoluchitNastroikiUvedomleniyQuery;
use App\Notification\Business\ReadModel\NastroikaUvedomleniyaReadModel;
use App\Notification\Business\UseCase\PoluchitSpisokUvedomleniyQuery;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
final class NotificationController extends AbstractController
{
    public function __construct(private readonly ?string $vapidPublicKey = null)
    {
    }

    #[OA\Get(
        path: '/api/notifications',
        summary: 'Уведомления текущего пользователя',
        tags: ['Notification'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50, minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'onlyActionable', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Список уведомлений и число непрочитанных'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('', methods: ['GET'])]
    public function list(Request $request, PoluchitSpisokUvedomleniyQuery $query): JsonResponse
    {
        $limit = (int) $request->query->get('limit', '50');
        $onlyActionable = $request->query->getBoolean('onlyActionable');

        return $this->json(Response::success($query->handle($limit, $onlyActionable)->toArray()));
    }

    #[OA\Get(
        path: '/api/notifications/unread-count',
        summary: 'Число непрочитанных уведомлений текущего пользователя',
        tags: ['Notification'],
        responses: [
            new OA\Response(response: 200, description: 'Счётчик непрочитанных'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/unread-count', methods: ['GET'])]
    public function unreadCount(PoluchitKolichestvoNeprochitannihQuery $query): JsonResponse
    {
        return $this->json(Response::success(['count' => $query->handle()]));
    }

    #[OA\Post(
        path: '/api/notifications/{id}/read',
        summary: 'Отметить уведомление прочитанным',
        tags: ['Notification'],
        responses: [
            new OA\Response(response: 200, description: 'Уведомление прочитано'),
            new OA\Response(response: 404, description: 'Уведомление не найдено'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/{id}/read', methods: ['POST'])]
    public function read(string $id, OtmetitUvedomlenieProchitannimUseCase $useCase): JsonResponse
    {
        return $this->json(Response::success(['updated' => $useCase->handle($id)]));
    }

    #[OA\Post(
        path: '/api/notifications/read-all',
        summary: 'Отметить все уведомления прочитанными',
        tags: ['Notification'],
        responses: [
            new OA\Response(response: 200, description: 'Количество обновлённых уведомлений'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/read-all', methods: ['POST'])]
    public function readAll(OtmetitVseUvedomleniyaProchitannimiUseCase $useCase): JsonResponse
    {
        return $this->json(Response::success(['updated' => $useCase->handle()]));
    }

    #[OA\Get(
        path: '/api/notifications/preferences',
        summary: 'Настройки типов уведомлений текущего пользователя',
        tags: ['Notification'],
        responses: [
            new OA\Response(response: 200, description: 'Список типов и переключателей'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/preferences', methods: ['GET'])]
    public function preferences(PoluchitNastroikiUvedomleniyQuery $query): JsonResponse
    {
        $items = array_map(
            static fn (NastroikaUvedomleniyaReadModel $item): array => $item->toArray(),
            $query->handle(),
        );

        return $this->json(Response::success($items));
    }

    #[OA\Put(
        path: '/api/notifications/preferences/{type}',
        summary: 'Изменить настройку типа уведомлений',
        tags: ['Notification'],
        responses: [
            new OA\Response(response: 200, description: 'Настройка сохранена'),
            new OA\Response(response: 400, description: 'Некорректное тело или системный тип'),
            new OA\Response(response: 404, description: 'Неизвестный тип'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/preferences/{type}', methods: ['PUT'])]
    public function updatePreference(
        string $type,
        Request $request,
        IzmenitNastroikuUvedomleniyaUseCase $useCase,
    ): JsonResponse {
        $tip = TipUvedomleniya::tryFrom($type);

        if ($tip === null) {
            throw new NotFoundHttpException('notification.type_not_found');
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !array_key_exists('enabled', $data)) {
            throw new BadRequestHttpException('notification.preference.invalid_payload');
        }

        $enabled = $data['enabled'];

        if (!is_bool($enabled)) {
            throw new BadRequestHttpException('notification.preference.invalid_payload');
        }

        $useCase->handle(new IzmenitNastroikuUvedomleniya($tip, $enabled));

        return $this->json(Response::successStub());
    }

    #[OA\Get(
        path: '/api/notifications/push/public-key',
        summary: 'VAPID public key для подписки на Web Push',
        tags: ['Notification'],
        responses: [
            new OA\Response(response: 200, description: 'Публичный VAPID-ключ'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/push/public-key', methods: ['GET'])]
    public function pushPublicKey(): JsonResponse
    {
        return $this->json(Response::success(['publicKey' => $this->vapidPublicKey ?? '']));
    }

    #[OA\Post(
        path: '/api/notifications/push/subscribe',
        summary: 'Сохранить подписку браузера на Web Push',
        tags: ['Notification'],
        responses: [
            new OA\Response(response: 200, description: 'Подписка сохранена'),
            new OA\Response(response: 400, description: 'Некорректный endpoint или ключи'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/push/subscribe', methods: ['POST'])]
    public function subscribe(PodpisatsyaNaPush $command, PodpisatsyaNaPushUseCase $useCase): JsonResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/notifications/push/unsubscribe',
        summary: 'Удалить подписку браузера на Web Push',
        tags: ['Notification'],
        responses: [
            new OA\Response(response: 200, description: 'Подписка удалена'),
            new OA\Response(response: 400, description: 'Некорректный endpoint'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/push/unsubscribe', methods: ['POST'])]
    public function unsubscribe(OtpisatsyaOtPush $command, OtpisatsyaOtPushUseCase $useCase): JsonResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }
}
