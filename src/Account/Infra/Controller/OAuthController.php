<?php

declare(strict_types=1);

namespace App\Account\Infra\Controller;

use App\Account\Business\Command\ObmenyatOAuthKod;
use App\Account\Business\UseCase\NachatOAuthAvtorizaciyuUseCase;
use App\Account\Business\UseCase\ObmenyatOAuthKodUseCase;
use App\Account\Business\UseCase\PoluchitOAuthProvayderyQuery;
use App\Account\Business\UseCase\ZavershitOAuthAvtorizaciyuUseCase;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/oauth')]
final class OAuthController extends AbstractController
{
    public function __construct(private readonly string $oauthFrontendUrl)
    {
    }

    #[OA\Get(
        path: '/api/oauth/providers',
        summary: 'Список настроенных OAuth-провайдеров',
        tags: ['Account'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список провайдеров',
                content: new OA\JsonContent(ref: '#/components/schemas/OAuthProviderList')
            ),
        ]
    )]
    #[Route('/providers', methods: ['GET'])]
    public function providers(PoluchitOAuthProvayderyQuery $query): Response
    {
        return $this->json(\App\Utils\Model\Reponse\Response::success($query->handle()));
    }

    #[OA\Get(
        path: '/api/oauth/{provider}/redirect',
        summary: 'Начать OAuth-вход',
        tags: ['Account'],
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'provider',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['google', 'github'])
            ),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Редирект на страницу провайдера'),
        ]
    )]
    #[Route('/{provider}/redirect', methods: ['GET'])]
    public function redirectToProvider(
        string $provider,
        Request $request,
        NachatOAuthAvtorizaciyuUseCase $useCase,
    ): Response {
        try {
            $url = $useCase->handle($provider, $request->query->get('locale', 'ru'));
        } catch (\DomainException $exception) {
            return $this->errorRedirect($exception->getMessage());
        }

        return new RedirectResponse($url);
    }

    #[OA\Get(
        path: '/api/oauth/{provider}/callback',
        summary: 'Callback OAuth-провайдера',
        tags: ['Account'],
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'provider',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['google', 'github'])
            ),
            new OA\Parameter(name: 'code', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Редирект на фронтенд с одноразовым кодом или ошибкой'),
        ]
    )]
    #[Route('/{provider}/callback', methods: ['GET'])]
    public function callback(
        string $provider,
        Request $request,
        ZavershitOAuthAvtorizaciyuUseCase $useCase,
    ): Response {
        $code = $request->query->get('code', '');
        $state = $request->query->get('state', '');

        if ($code === '' || $state === '') {
            return $this->errorRedirect('oauth.state.invalid');
        }

        try {
            $url = $useCase->handle($provider, $code, $state);
        } catch (\DomainException $exception) {
            return $this->errorRedirect($exception->getMessage());
        } catch (\Throwable) {
            return $this->errorRedirect('oauth.exchange.failed');
        }

        return new RedirectResponse($url);
    }

    #[OA\Post(
        path: '/api/oauth/exchange',
        summary: 'Обменять одноразовый OAuth-код на токены',
        tags: ['Account'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/OAuthExchange')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Пара токенов и данные пользователя'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/exchange', methods: ['POST'])]
    public function exchange(ObmenyatOAuthKod $command, ObmenyatOAuthKodUseCase $useCase): Response
    {
        $result = $useCase->handle($command);

        return $this->json(\App\Utils\Model\Reponse\Response::success([
            'token' => $result->token,
            'refreshToken' => $result->refreshToken,
            'user' => $result->user,
        ]));
    }

    private function errorRedirect(string $error): RedirectResponse
    {
        return new RedirectResponse(
            rtrim($this->oauthFrontendUrl, '/') . '/login?oauthError=' . urlencode($error),
        );
    }
}
