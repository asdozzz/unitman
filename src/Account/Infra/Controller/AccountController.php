<?php

namespace App\Account\Infra\Controller;
use App\Account\Business\Command\BlockByAdmin;
use App\Account\Business\Command\ChangeEmailByAdmin;
use App\Account\Business\Command\ChangeMyLocale;
use App\Account\Business\Command\ChangeMyNickname;
use App\Account\Business\Command\ChangeMyPassword;
use App\Account\Business\Command\ChangeNicknameByAdmin;
use App\Account\Business\Command\ChangePasswordByAdmin;
use App\Account\Business\Command\ChangeRoleByAdmin;
use App\Account\Business\Command\PoluchitSpisokVsehPolzovateleiDlyAdministrirovaniya;
use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Command\UnblockByAdmin;
use App\Account\Business\UseCase\BlockByAdminUseCase;
use App\Account\Business\UseCase\ChangeEmailByAdminUseCase;
use App\Account\Business\UseCase\ChangeMyLocaleUseCase;
use App\Account\Business\UseCase\ChangeMyNicknameUseCase;
use App\Account\Business\UseCase\ChangeMyPasswordUseCase;
use App\Account\Business\UseCase\ChangeNicknameByAdminUseCase;
use App\Account\Business\UseCase\ChangePasswordByAdminUseCase;
use App\Account\Business\UseCase\ChangeRoleByAdminUseCase;
use App\Account\Business\UseCase\PoluchitNastroikiAccountaUseCase;
use App\Account\Business\UseCase\PoluchitSpisokVsehPolzovateleiDlyAdministrirovaniyaQuery;
use App\Account\Business\UseCase\PoluchitSpisokVsehPolzovateleiQuery;
use App\Account\Business\UseCase\RegisterAccountUseCase;
use App\Account\Business\UseCase\UnblockByAdminUseCase;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/account')]
final class AccountController extends AbstractController
{
    #[OA\Post(
        path: '/api/account/register',
        summary: 'Регистрация аккаунта',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterAccount')
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
    #[Route('/register', methods: ['POST'])]
    function register(RegisterAccount $command, RegisterAccountUseCase $useCase): Response
    {
        $id = $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success(['id' => $id]));
    }

    #[OA\Post(
        path: '/api/account/changePasswordByAdmin',
        summary: 'Изменить пароль аккаунта (администратор)',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangePasswordByAdmin')
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
    #[Route('/changePasswordByAdmin', methods: ['POST'])]
    function changePasswordByAdmin(ChangePasswordByAdmin $command, ChangePasswordByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/account/changeMyPassword',
        summary: 'Изменить свой пароль',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangeMyPassword')
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
    #[Route('/changeMyPassword', methods: ['POST'])]
    function changeMyPassword(ChangeMyPassword $command, ChangeMyPasswordUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/account/changeNicknameByAdmin',
        summary: 'Изменить никнейм аккаунта (администратор)',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangeNicknameByAdmin')
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
    #[Route('/changeNicknameByAdmin', methods: ['POST'])]
    function changeNicknameByAdmin(ChangeNicknameByAdmin $command, ChangeNicknameByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/account/changeRoleByAdmin',
        summary: 'Изменить роль аккаунта (администратор)',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangeRoleByAdmin')
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
    #[Route('/changeRoleByAdmin', methods: ['POST'])]
    function changeRoleByAdmin(ChangeRoleByAdmin $command, ChangeRoleByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/account/changeMyNickname',
        summary: 'Изменить свой никнейм',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangeMyNickname')
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
    #[Route('/changeMyNickname', methods: ['POST'])]
    function changeMyNickname(ChangeMyNickname $command, ChangeMyNicknameUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/account/changeEmailByAdmin',
        summary: 'Изменить email аккаунта (администратор)',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangeEmailByAdmin')
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
    #[Route('/changeEmailByAdmin', methods: ['POST'])]
    function changeEmail(ChangeEmailByAdmin $command, ChangeEmailByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/account/blockByAdmin',
        summary: 'Заблокировать аккаунт (администратор)',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/BlockByAdmin')
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
    #[Route('/blockByAdmin', methods: ['POST'])]
    function block(BlockByAdmin $command, BlockByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/account/unblockByAdmin',
        summary: 'Разблокировать аккаунт (администратор)',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UnblockByAdmin')
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
    #[Route('/unblockByAdmin', methods: ['POST'])]
    function unblock(UnblockByAdmin $command, UnblockByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Get(
        path: '/api/account/poluchitVsehPolzovatelei',
        summary: 'Список всех пользователей',
        tags: ['Account'],
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
    #[Route('/poluchitVsehPolzovatelei', methods: ['GET'])]
    function poluchitVsehPolzovatelei(PoluchitSpisokVsehPolzovateleiQuery $useCase): Response
    {
        $result = $useCase->handle();
        return $this->json(\App\Utils\Model\Reponse\Response::success($result));
    }

    #[OA\Post(
        path: '/api/account/poluchitVsehPolzovateleiDlyAdministrorovaniya',
        summary: 'Список всех пользователей для администрирования',
        tags: ['Account'],
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
    #[Route('/poluchitVsehPolzovateleiDlyAdministrorovaniya', methods: ['POST'])]
    function poluchitVsehPolzovateleiDlyAdministrorovaniya(#[MapRequestPayload] PoluchitSpisokVsehPolzovateleiDlyAdministrirovaniya $command, PoluchitSpisokVsehPolzovateleiDlyAdministrirovaniyaQuery $useCase): Response
    {
        $result = $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($result));
    }

    #[OA\Post(
        path: '/api/account/changeMyLocale',
        summary: 'Изменить свою локаль',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangeMyLocale')
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
    #[Route('/changeMyLocale', methods: ['POST'])]
    function changeMyLocale(ChangeMyLocale $command, ChangeMyLocaleUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Get(
        path: '/api/account/poluchitNastroikiPolzovatelya',
        summary: 'Настройки текущего пользователя',
        tags: ['Account'],
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
    #[Route('/poluchitNastroikiPolzovatelya', methods: ['GET'])]
    function poluchitNastroikiPolzovatelya(PoluchitNastroikiAccountaUseCase $useCase): Response
    {
        return $this->json(\App\Utils\Model\Reponse\Response::success($useCase->handle()));
    }
}
