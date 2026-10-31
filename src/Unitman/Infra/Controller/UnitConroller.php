<?php

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Unit\GetMyUnits;
use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Command\Unit\GetUnitReadModelById;
use App\Unitman\Business\Command\Unit\GetUnitRunnerJobs;
use App\Unitman\Business\Command\Unit\NaitiDubliUnita;
use App\Unitman\Business\Command\Unit\PoluchitShagiZadachiUnita;
use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\OstanovitUnit;
use App\Unitman\Business\Command\Unit\PeresobratModelSpisokUnitov;
use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Command\Unit\PoluchitKonfigIzHranilisha;
use App\Unitman\Business\Command\Unit\PoluchitKonfigUnita;
use App\Unitman\Business\Command\Unit\PoluchitPeremenieUnita;
use App\Unitman\Business\Command\Unit\PoluchitSsylkuPoPomoschniku;
use App\Unitman\Business\Command\Unit\PometitKakSlomanii;
use App\Unitman\Business\Command\Unit\ProveritKonteinerUnita;
use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\UdalitSlomaniyUnit;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Command\Unit\VipolnitDeistviye;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\UseCase\Unit\DobavitProzesObnovleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesOstanovkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesPodgotovkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesSborkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesSbrosaPodgotovkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesVipolneniyaDeistviyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\GetMyUnitsQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitByIdQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitListQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitRunnerJobsQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitRunnerJobStepsQuery;
use App\Unitman\Business\UseCase\Unit\NaitiDubliUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\PoluchitKonfigIzHranilishaUseCase;
use App\Unitman\Business\UseCase\Unit\PoluchitKonfigUnitaQuery;
use App\Unitman\Business\UseCase\Unit\PoluchitPeremenieUnitaQuery;
use App\Unitman\Business\UseCase\Unit\PoluchitSsylkuPoPomoschnikuQuery;
use App\Unitman\Business\UseCase\Unit\PometitKakSlomaniiUseCase;
use App\Unitman\Business\UseCase\Unit\ProveritKonteinerUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitSlomaniyUnitUseCase;
use App\Unitman\Business\UseCase\Unit\ZapolnitPeremenieUnitaUseCase;
use App\Unitman\Infra\Projection\SpisokUnitovProjection;
use App\Unitman\Infra\Service\RebuildService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/unit')]
final class UnitConroller extends AbstractController
{
    #[OA\Post(
        path: '/api/unit/sozdat',
        summary: 'Создать юнит',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SozdatUnit')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/sozdat', methods: ['POST'])]
    public function sozdat(SozdatUnit $command, SozdatUnitUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/list',
        summary: 'Список юнитов с фильтрацией',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetUnitList')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/list', methods: ['POST'])]
    public function list(#[MapRequestPayload] GetUnitList $command, GetUnitListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/unit/naitiDubliUnita',
        summary: 'Найти дубли юнитов по проектe и ветке',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/NaitiDubliUnita')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/naitiDubliUnita', methods: ['POST'])]
    public function naitiDubliUnita(NaitiDubliUnita $command, NaitiDubliUnitaUseCase $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/unit/obnovit',
        summary: 'Юнит по идентификатору',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetUnitReadModelById')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/obnovit', methods: ['POST'])]
    public function read(GetUnitReadModelById $command, GetUnitByIdQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/unit/moi',
        summary: 'Мои юниты',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetMyUnits')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/moi', methods: ['POST'])]
    public function moi(GetMyUnits $command, GetMyUnitsQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/unit/sobrat',
        summary: 'Собрать юнит',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SobratUnit')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/sobrat', methods: ['POST'])]
    public function sobrat(SobratUnit $command, DobavitProzesSborkiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/obnovitKodUnita',
        summary: 'Обновить код юнита',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ObnovitKodUnita')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/obnovitKodUnita', methods: ['POST'])]
    public function obnovitKodUnita(ObnovitKodUnita $command, DobavitProzesObnovleniyaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/zapolnitPeremenie',
        summary: 'Заполнить переменные юнита',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ZapolnitPeremenieUnita')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/zapolnitPeremenie', methods: ['POST'])]
    public function zapolnitPeremenie(ZapolnitPeremenieUnita $command, ZapolnitPeremenieUnitaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/podgotovit',
        summary: 'Подготовить юнит к запуску',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PodgotovitUnitKZapusku')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/podgotovit', methods: ['POST'])]
    public function podgotovit(PodgotovitUnitKZapusku $command, DobavitProzesPodgotovkiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/sbrositPodgotovku',
        summary: 'Сбросить подготовку юнита',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SbrositPodgotovkuUnita')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/sbrositPodgotovku', methods: ['POST'])]
    public function sbrositPodgotovku(SbrositPodgotovkuUnita $command, DobavitProzesSbrosaPodgotovkiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/zapustit',
        summary: 'Запустить юнит',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ZapustitUnit')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/zapustit', methods: ['POST'])]
    public function zapustit(ZapustitUnit $command, DobavitProzesZapuskaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/ostanovit',
        summary: 'Остановить юнит',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/OstanovitUnit')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/ostanovit', methods: ['POST'])]
    public function ostanovit(OstanovitUnit $command, DobavitProzesOstanovkiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/udalit',
        summary: 'Удалить юнит',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UdalitUnit')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/udalit', methods: ['POST'])]
    public function udalit(UdalitUnit $command, DobavitProzesUdaleniyaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/udalitSlomaniyUnit',
        summary: 'Удалить сломанный юнит',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UdalitSlomaniyUnit')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/udalitSlomaniyUnit', methods: ['POST'])]
    public function udalitSlomaniyUnit(UdalitSlomaniyUnit $command, UdalitSlomaniyUnitUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/poluchitKonfigUnita',
        summary: 'Конфигурация юнита',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitKonfigUnita')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/poluchitKonfigUnita', methods: ['POST'])]
    public function poluchitKonfigUnita(PoluchitKonfigUnita $command, PoluchitKonfigUnitaQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/poluchitPeremenieUnita',
        summary: 'Переменные юнита',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitPeremenieUnita')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/poluchitPeremenieUnita', methods: ['POST'])]
    public function poluchitPeremenieUnita(PoluchitPeremenieUnita $command, PoluchitPeremenieUnitaQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/poluchitSsylkuPoPomoschniku',
        summary: 'Ссылка на помощника (кросс-сервисный адрес)',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitSsylkuPoPomoschniku')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/poluchitSsylkuPoPomoschniku', methods: ['POST'])]
    public function poluchitSsylkuPoPomoschniku(PoluchitSsylkuPoPomoschniku $command, PoluchitSsylkuPoPomoschnikuQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Throwable $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/poluchitVipolnenieZadachiRunnera',
        summary: "Выполнение задачи runner'а для юнита",
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetUnitRunnerJobs')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/poluchitVipolnenieZadachiRunnera', methods: ['POST'])]
    public function poluchitVipolnenieZadachiRunnera(GetUnitRunnerJobs $command, GetUnitRunnerJobsQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/poluchitShagiZadachiRunnera',
        summary: 'Шаги задачи runner\'а',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitShagiZadachiUnita')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/poluchitShagiZadachiRunnera', methods: ['POST'])]
    public function poluchitShagiZadachiRunnera(PoluchitShagiZadachiUnita $command, GetUnitRunnerJobStepsQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/vipolnitDeistviye',
        summary: 'Выполнить действие над юнитом',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/VipolnitDeistviye')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/vipolnitDeistviye', methods: ['POST'])]
    public function vipolnitDeistviye(VipolnitDeistviye $command, DobavitProzesVipolneniyaDeistviyaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/proveritKonteinerUnita',
        summary: 'Проверить контейнер юнита',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProveritKonteinerUnita')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/proveritKonteinerUnita', methods: ['POST'])]
    public function proveritKonteinerUnita(ProveritKonteinerUnita $command, ProveritKonteinerUnitaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/poluchitKonfigIzHranilisha',
        summary: 'Конфигурация из хранилища по проекту и ветке',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitKonfigIzHranilisha')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/poluchitKonfigIzHranilisha', methods: ['POST'])]
    public function poluchitKonfigIzHranilisha(PoluchitKonfigIzHranilisha $command, PoluchitKonfigIzHranilishaUseCase $useCase): Response
    {
        try {
            $data = $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/rebuildSpisokUnitov',
        summary: 'Пересобрать проекцию списка юнитов',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PeresobratModelSpisokUnitov')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/rebuildSpisokUnitov', methods: ['POST'])]
    public function rebuildSpisokUnitov(PeresobratModelSpisokUnitov $command, RebuildService $service): Response
    {
        try {
            $service->handle(SpisokUnitovProjection::CODE, $command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Throwable $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/unit/pometitKakSlomanii',
        summary: 'Пометить юнит как сломанный',
        tags: ['Unit'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PometitKakSlomanii')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/pometitKakSlomanii', methods: ['POST'])]
    public function pometitKakSlomanii(PometitKakSlomanii $command, PometitKakSlomaniiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }
}
