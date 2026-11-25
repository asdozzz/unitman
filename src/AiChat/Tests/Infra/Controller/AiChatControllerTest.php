<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Infra\Controller;

use App\Account\Api\AccountApi;
use App\Account\Business\Model\JWTUser;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Model\OpenCode\ProvayderOpenCode;
use App\Account\Infra\Adapter\SymfonySecurityService;
use App\Account\Infra\Repository\JWTUserRepository;
use App\AiChat\Infra\Controller\AiChatController;
use App\AiChat\Infra\Service\OpenCodeChatProxy;
use App\AiChat\Tests\Support\InMemoryOpenCodeCredentialRepository;
use App\AiChat\Tests\Support\PlainTextCipher;
use App\AiChat\Tests\Support\RecordingHttpClient;
use App\AiChat\Tests\Support\RecordingLogger;
use App\Utils\Service\ExceptionTranslator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Контракт POST /api/ai/chat и отсутствие утечки состояния между запросами.
 *
 * Контроллер собирается вручную с настоящим AccountApi (in-memory репозиторий
 * и plain-text шифр) и записывающим HttpClient. Это позволяет проверять
 * заголовки и тела исходящих запросов без БД и сети.
 */
final class AiChatControllerTest extends TestCase
{
    private const FALLBACK_URL = 'https://fallback.example/v1';
    private const TIMEOUT = 7.5;

    private TokenStorage $tokenStorage;
    private InMemoryOpenCodeCredentialRepository $repository;
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->repository = new InMemoryOpenCodeCredentialRepository();
        $this->logger = new RecordingLogger();
    }

    public function test_uspeh_retransliruet_otvet_i_beret_url_iz_rekvizitov(): void
    {
        $this->dobavitRekvizity('cred-one', 'acc-1', 'https://one.example/v1', 'sk-one', ['model-a']);
        $user = $this->podmenitPolzovatelya('acc-1');

        $client = new RecordingHttpClient(
            new MockResponse('{"choices":[{"message":{"content":"ok"}}]}'),
        );
        $controller = $this->sozdatKontroller($client);

        $response = $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'Создай проект']],
        ]));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ok', $body['choices'][0]['message']['content']);

        self::assertCount(1, $client->requests);
        $request = $client->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://one.example/v1/chat/completions', $request['url']);
        self::assertSame('Bearer sk-one', $request['options']['headers']['Authorization']);
        self::assertSame('model-a', $request['options']['json']['model']);
        self::assertSame(self::TIMEOUT, $request['options']['timeout']);
    }

    public function test_401_bez_autentificirovannogo_polzovatelya(): void
    {
        $client = new RecordingHttpClient();
        $controller = $this->sozdatKontroller($client);

        $response = $controller(null, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('account.not_authenticated', $this->kodOshibki($response));
        self::assertSame([], $client->requests);
    }

    public function test_422_nekorrektniy_payload(): void
    {
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient();
        $controller = $this->sozdatKontroller($client);

        $response = $controller($user, $this->zapros([
            'messages' => [],
        ]));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('ai.chat.invalid_payload', $this->kodOshibki($response));
        self::assertSame([], $client->requests);
    }

    public function test_422_net_rekvizitov_u_polzovatelya(): void
    {
        $user = $this->podmenitPolzovatelya('acc-bez-rekvizitov');
        $client = new RecordingHttpClient();
        $controller = $this->sozdatKontroller($client);

        $response = $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('ai.chat.credential_required', $this->kodOshibki($response));
        self::assertSame([], $client->requests);
    }

    public function test_422_model_nedostupna_v_rekvizitah(): void
    {
        $this->dobavitRekvizity('cred-one', 'acc-1', 'https://one.example/v1', 'sk-one', ['model-a']);
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient();
        $controller = $this->sozdatKontroller($client);

        $response = $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'model' => 'model-z',
        ]));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('account.opencode_credential.model_not_available', $this->kodOshibki($response));
        self::assertSame([], $client->requests);
    }

    public function test_502_pri_transportnoy_oshibke(): void
    {
        $this->dobavitRekvizity('cred-one', 'acc-1', 'https://one.example/v1', 'sk-one', ['model-a']);
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(
            new MockResponse('', ['error' => 'Connection refused']),
        );
        $controller = $this->sozdatKontroller($client);

        $response = $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame('ai.chat.upstream_unavailable', $this->kodOshibki($response));
    }

    public function test_502_pri_nekorrektnom_otvete_upstream(): void
    {
        $this->dobavitRekvizity('cred-one', 'acc-1', 'https://one.example/v1', 'sk-one', ['model-a']);
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(
            new MockResponse('not-json', ['http_code' => 200]),
        );
        $controller = $this->sozdatKontroller($client);

        $response = $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame('ai.chat.upstream_invalid_response', $this->kodOshibki($response));
    }

    public function test_pustoy_url_rekvizitov_ispolzuet_env_fallback(): void
    {
        $this->repository->save(OpenCodeCredential::restore(
            'cred-empty-url',
            'acc-1',
            'legacy',
            'custom',
            '',
            ['model-a'],
            'model-a',
            'sk-empty',
            'sk-***',
            OpenCodeCredential::CIPHER_VERSION,
            0,
            0,
        ));
        $user = $this->podmenitPolzovatelya('acc-1');

        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        self::assertSame(self::FALLBACK_URL.'/chat/completions', $client->requests[0]['url']);
    }

    /**
     * Главный тест stateless-работы под RoadRunner: два последовательных
     * запроса с разными пользователями, реквизитами и моделями не должны
     * обменяться ключами, заголовками, телами или URL.
     */
    public function test_dva_zaprosa_s_raznimi_rekvizitami_ne_teku_drug_v_druga(): void
    {
        $this->dobavitRekvizity('cred-one', 'acc-1', 'https://one.example/v1', 'sk-one', ['model-a', 'model-b']);
        $this->dobavitRekvizity('cred-two', 'acc-2', 'https://two.example/v1', 'sk-two', ['model-x']);

        $client = new RecordingHttpClient(
            new MockResponse('{"choices":[{"message":{"content":"first"}}]}'),
            new MockResponse('{"choices":[{"message":{"content":"second"}}]}'),
        );
        $controller = $this->sozdatKontroller($client);

        $userOne = $this->podmenitPolzovatelya('acc-1');
        $responseOne = $controller($userOne, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'first-request']],
            'model' => 'model-b',
            'credentialId' => 'cred-one',
        ]));

        $userTwo = $this->podmenitPolzovatelya('acc-2');
        $responseTwo = $controller($userTwo, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'second-request']],
            'credentialId' => 'cred-two',
        ]));

        self::assertSame(Response::HTTP_OK, $responseOne->getStatusCode());
        self::assertSame(Response::HTTP_OK, $responseTwo->getStatusCode());
        self::assertCount(2, $client->requests);

        [$first, $second] = $client->requests;

        self::assertSame('https://one.example/v1/chat/completions', $first['url']);
        self::assertSame('Bearer sk-one', $first['options']['headers']['Authorization']);
        self::assertSame('model-b', $first['options']['json']['model']);
        self::assertSame('first-request', $first['options']['json']['messages'][0]['content']);

        self::assertSame('https://two.example/v1/chat/completions', $second['url']);
        self::assertSame('Bearer sk-two', $second['options']['headers']['Authorization']);
        self::assertSame('model-x', $second['options']['json']['model']);
        self::assertSame('second-request', $second['options']['json']['messages'][0]['content']);

        self::assertNotSame(
            $first['options']['headers']['Authorization'],
            $second['options']['headers']['Authorization'],
        );
    }

    public function test_openai_ispolzuet_bearer(): void
    {
        $this->dobavitRekvizity(
            'cred-openai',
            'acc-1',
            'https://api.openai.com/v1',
            'sk-openai',
            ['model-a'],
            ProvayderOpenCode::OPENAI->value,
        );
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        $headers = $client->requests[0]['options']['headers'];
        self::assertSame('Bearer sk-openai', $headers['Authorization']);
        self::assertArrayNotHasKey('x-api-key', $headers);
        self::assertArrayNotHasKey('anthropic-version', $headers);
    }

    public function test_opencode_go_peredayot_x_opencode_session_iz_zaprosa(): void
    {
        $this->dobavitRekvizity(
            'cred-go',
            'acc-1',
            ProvayderOpenCode::OPENCODE_GO->baseUrl(),
            'sk-go',
            ['model-a'],
            ProvayderOpenCode::OPENCODE_GO->value,
        );
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'sessionId' => 'dialog-1.abc:def_2',
        ]));

        self::assertSame(
            'dialog-1.abc:def_2',
            $client->requests[0]['options']['headers']['x-opencode-session'],
        );
    }

    public function test_opencode_go_bez_session_id_podstavlyaet_uuid_v4(): void
    {
        $this->dobavitRekvizity(
            'cred-go',
            'acc-1',
            ProvayderOpenCode::OPENCODE_GO->baseUrl(),
            'sk-go',
            ['model-a'],
            ProvayderOpenCode::OPENCODE_GO->value,
        );
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        $session = $client->requests[0]['options']['headers']['x-opencode-session'];
        self::assertIsString($session);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $session,
        );
    }

    public function test_nesessiya_ne_peredayotsya_drugim_provayderam(): void
    {
        $this->dobavitRekvizity(
            'cred-openai',
            'acc-1',
            'https://api.openai.com/v1',
            'sk-openai',
            ['model-a'],
            ProvayderOpenCode::OPENAI->value,
        );
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'sessionId' => 'dialog-1',
        ]));

        self::assertArrayNotHasKey('x-opencode-session', $client->requests[0]['options']['headers']);
    }

    public function test_422_nekorrektniy_session_id(): void
    {
        $this->dobavitRekvizity(
            'cred-go',
            'acc-1',
            ProvayderOpenCode::OPENCODE_GO->baseUrl(),
            'sk-go',
            ['model-a'],
            ProvayderOpenCode::OPENCODE_GO->value,
        );
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient();
        $controller = $this->sozdatKontroller($client);

        $response = $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'sessionId' => "bad\nsession",
        ]));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('ai.chat.invalid_payload', $this->kodOshibki($response));
        self::assertSame([], $client->requests);
    }

    public function test_anthropic_ispolzuet_x_api_key_i_versiyu_bez_authorization(): void
    {
        $this->dobavitRekvizity(
            'cred-anthropic',
            'acc-1',
            'https://api.anthropic.com/v1',
            'sk-ant',
            ['claude-3'],
            ProvayderOpenCode::ANTHROPIC->value,
        );
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        $headers = $client->requests[0]['options']['headers'];
        self::assertSame('sk-ant', $headers['x-api-key']);
        self::assertSame('2023-06-01', $headers['anthropic-version']);
        self::assertArrayNotHasKey('Authorization', $headers);
    }

    public function test_ollama_bez_avtorizacii(): void
    {
        $this->dobavitRekvizity(
            'cred-ollama',
            'acc-1',
            'http://localhost:11434/v1',
            'sk-ollama',
            ['llama3'],
            ProvayderOpenCode::OLLAMA->value,
        );
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        $headers = $client->requests[0]['options']['headers'];
        self::assertArrayNotHasKey('Authorization', $headers);
        self::assertArrayNotHasKey('x-api-key', $headers);
    }

    public function test_custom_bez_avtorizacii(): void
    {
        $this->dobavitRekvizity(
            'cred-custom',
            'acc-1',
            'https://custom.example/v1',
            'sk-custom',
            ['model-a'],
            ProvayderOpenCode::CUSTOM->value,
        );
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        $headers = $client->requests[0]['options']['headers'];
        self::assertArrayNotHasKey('Authorization', $headers);
        self::assertArrayNotHasKey('x-api-key', $headers);
    }

    public function test_opencode_ispolzuet_v1_v_bazovom_url(): void
    {
        $this->dobavitRekvizity(
            'cred-opencode',
            'acc-1',
            ProvayderOpenCode::OPENCODE->baseUrl(),
            'sk-opencode',
            ['model-a'],
            ProvayderOpenCode::OPENCODE->value,
        );
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]));

        self::assertSame(
            'https://api.opencode.ai/v1/chat/completions',
            $client->requests[0]['url'],
        );
    }

    public function test_400_upstream_retransliruet_status_i_telo_oshibki(): void
    {
        $this->dobavitRekvizity('cred-one', 'acc-1', 'https://one.example/v1', 'sk-one', ['model-a']);
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse(
            '{"error":{"message":"Model not found","code":"model_not_found"}}',
            ['http_code' => 400],
        ));
        $controller = $this->sozdatKontroller($client);

        $response = $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'Создай проект']],
        ]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Model not found', $body['error']['message']);
        self::assertSame('model_not_found', $body['error']['code']);
    }

    public function test_4xx_upstream_pishet_warning_bez_sekretov_i_soobsheniy(): void
    {
        $this->dobavitRekvizity('cred-one', 'acc-1', 'https://one.example/v1', 'sk-secret', ['model-a']);
        $user = $this->podmenitPolzovatelya('acc-1');
        $client = new RecordingHttpClient(new MockResponse(
            '{"error":{"message":"Model not found","code":"model_not_found"}}',
            ['http_code' => 400],
        ));
        $controller = $this->sozdatKontroller($client);

        $controller($user, $this->zapros([
            'messages' => [['role' => 'user', 'content' => 'Секретная переписка']],
        ]));

        self::assertCount(1, $this->logger->records);
        $record = $this->logger->records[0];

        self::assertSame('warning', $record['level']);
        self::assertSame(
            ['status', 'provider', 'error.message', 'error.code'],
            array_keys($record['context']),
        );
        self::assertSame(400, $record['context']['status']);
        self::assertSame('openai', $record['context']['provider']);
        self::assertSame('Model not found', $record['context']['error.message']);
        self::assertSame('model_not_found', $record['context']['error.code']);

        $logged = json_encode($record, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('sk-secret', $logged);
        self::assertStringNotContainsString('Authorization', $logged);
        self::assertStringNotContainsString('x-api-key', $logged);
        self::assertStringNotContainsString('Секретная переписка', $logged);
    }

    private function zapros(array $payload): Request
    {
        return Request::create(
            '/api/ai/chat',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function kodOshibki(JsonResponse $response): string
    {
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('fail', $body['status']);

        return $body['data']['code'];
    }

    private function podmenitPolzovatelya(string $accountId): JWTUser
    {
        $user = new JWTUser($accountId, $accountId.'@example.com', 'hash', ['ROLE_USER']);
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));

        return $user;
    }

    /**
     * @param list<string> $models
     */
    private function dobavitRekvizity(
        string $credentialId,
        string $accountId,
        string $url,
        string $apiKey,
        array $models,
        string $provider = ProvayderOpenCode::OPENAI->value,
    ): void {
        $this->repository->save(OpenCodeCredential::create(
            $credentialId,
            $accountId,
            'name-'.$credentialId,
            $provider,
            $url,
            $models,
            $models[0],
            $apiKey,
            'sk-***',
            0,
        ));
    }

    private function sozdatKontroller(RecordingHttpClient $client): AiChatController
    {
        $container = new class($this->tokenStorage) implements ContainerInterface {
            public function __construct(private readonly TokenStorageInterface $tokenStorage)
            {
            }

            public function get(string $id): mixed
            {
                return $this->tokenStorage;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $securityService = new SymfonySecurityService(new Security($container));

        /** @var JWTUserRepository $userRepository */
        $userRepository = (new \ReflectionClass(JWTUserRepository::class))->newInstanceWithoutConstructor();

        $accountApi = new AccountApi(
            $securityService,
            $userRepository,
            $this->repository,
            new PlainTextCipher(),
        );

        return new AiChatController(
            $accountApi,
            new OpenCodeChatProxy($client, $this->logger, self::TIMEOUT),
            new ExceptionTranslator(new IdentityTranslator(), 'en'),
            self::FALLBACK_URL,
        );
    }
}
