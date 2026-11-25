<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Infra\Controller;

use App\Account\Business\Model\JWTUser;
use App\Account\Business\ReadModel\OpenCodeCredentialSecretReadModel;
use App\Account\Business\ReadModel\ProviderSessionPolicyReadModel;
use App\AiChat\Business\Config\NastroikiAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\ReadModel\SessiyaAiChataReadModel;
use App\AiChat\Business\ReadModel\SpisokSessiyAiChataReadModel;
use App\AiChat\Business\UseCase\ArhivirovatSessiyuAiChataUseCase;
use App\AiChat\Business\UseCase\PoluchitSessiyuAiChataQuery;
use App\AiChat\Business\UseCase\PoluchitSpisokSessiyAiChataQuery;
use App\AiChat\Business\UseCase\SozdatSessiyuAiChataUseCase;
use App\AiChat\Business\UseCase\UdalitSessiyuAiChataUseCase;
use App\AiChat\Business\UseCase\VypolnitHodAiChataUseCase;
use App\AiChat\Infra\Controller\SessiiAiChataController;
use App\AiChat\Tests\Support\FakeRekvizityDlyaSessiiAiChata;
use App\AiChat\Tests\Support\FixedUuidGenerator;
use App\AiChat\Tests\Support\ImmediateTransactionManager;
use App\AiChat\Tests\Support\InMemorySessiyaAiChataRepository;
use App\AiChat\Tests\Support\InMemorySoobshenieAiChataRepository;
use App\AiChat\Tests\Support\RecordingOtpravitelChatZaprosov;
use App\Utils\Service\ExceptionTranslator;
use App\Utils\Service\TestClockService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * HTTP-контракт сессий AI-чата на in-memory портах.
 *
 * Контроллер собирается вручную с настоящими use case и фейковыми
 * репозиториями: это проверяет маппинг доменных ошибок в 401/404/409/422 и
 * единый конверт `FailResponse`, не поднимая БД и JWT. Подпись метода
 * `#[CurrentUser]` заменяется явной передачей пользователя либо `null`.
 */
final class SessiiAiChataControllerTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private InMemorySessiyaAiChataRepository $repository;
    private InMemorySoobshenieAiChataRepository $soobsheniya;
    private FakeRekvizityDlyaSessiiAiChata $rekvizity;
    private RecordingOtpravitelChatZaprosov $otpravitel;
    private TestClockService $clock;
    private SessiiAiChataController $controller;

    protected function setUp(): void
    {
        $this->repository = new InMemorySessiyaAiChataRepository();
        $this->soobsheniya = new InMemorySoobshenieAiChataRepository();
        $this->rekvizity = new FakeRekvizityDlyaSessiiAiChata('account-1');
        $this->rekvizity->credentialIds = ['cred-1'];
        $this->rekvizity->credentials['cred-1'] = new OpenCodeCredentialSecretReadModel(
            'cred-1',
            'account-1',
            'openai',
            'https://api.example.test',
            ['gpt-4o-mini'],
            'gpt-4o-mini',
            'gpt-4o-mini',
            'secret-key',
        );
        $this->rekvizity->policies['openai'] = new ProviderSessionPolicyReadModel(
            'openai',
            'none',
            null,
            300,
            true,
        );

        $this->otpravitel = new RecordingOtpravitelChatZaprosov();
        $this->clock = new TestClockService(new \DateTimeImmutable('@'.self::NOW));
        $nastroiki = new NastroikiAiChata(86_400, 300, 100);

        $this->controller = new SessiiAiChataController(
            new SozdatSessiyuAiChataUseCase(
                $this->repository,
                $this->rekvizity,
                new FixedUuidGenerator('sess-new', 'prov-new'),
                $nastroiki,
                $this->clock,
            ),
            new PoluchitSpisokSessiyAiChataQuery($this->repository, $this->clock),
            new PoluchitSessiyuAiChataQuery(
                $this->repository,
                $this->soobsheniya,
                $this->rekvizity,
                $this->clock,
            ),
            new ArhivirovatSessiyuAiChataUseCase(
                $this->repository,
                $this->soobsheniya,
                $this->rekvizity,
                $this->clock,
            ),
            new UdalitSessiyuAiChataUseCase($this->repository, $this->rekvizity),
            new VypolnitHodAiChataUseCase(
                $this->repository,
                $this->soobsheniya,
                $this->rekvizity,
                new FixedUuidGenerator(),
                $nastroiki,
                new ImmediateTransactionManager(),
                $this->otpravitel,
                'https://fallback.example.test',
                $this->clock,
            ),
            $this->rekvizity,
            new ExceptionTranslator(new IdentityTranslator(), 'en'),
        );
    }

    #[Test]
    public function list_bez_avtorizacii_401(): void
    {
        $response = $this->controller->list(null, $this->zapros('GET', '/api/ai/chat/sessions'));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('account.not_authenticated', $this->kodOshibki($response));
    }

    #[Test]
    public function show_neizvestnaya_sessiya_404(): void
    {
        $response = $this->controller->show('missing', $this->polzovatel());

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('ai.chat.session_not_found', $this->kodOshibki($response));
    }

    #[Test]
    public function show_chuzhaya_sessiya_404(): void
    {
        $this->repository->sessii['sess-other'] = $this->session('sess-other', 'account-2');

        $response = $this->controller->show('sess-other', $this->polzovatel());

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('ai.chat.session_not_found', $this->kodOshibki($response));
    }

    #[Test]
    public function list_peredaet_paginaciyu_poisk_i_flag_arhivnyh(): void
    {
        $this->repository->spisokRezultat = new SpisokSessiyAiChataReadModel(
            [$this->readModel()],
            10,
            5,
            1,
        );

        $request = $this->zapros('GET', '/api/ai/chat/sessions', null, [
            'limit' => '10',
            'offset' => '5',
            'search' => 'проект',
            'includeArchived' => '1',
        ]);

        $response = $this->controller->list($this->polzovatel(), $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->dannye($response);

        self::assertSame('account-1', $this->repository->spisokAccountId);
        $filtr = $this->repository->spisokFiltr;
        self::assertNotNull($filtr);
        self::assertSame(10, $filtr->limit);
        self::assertSame(5, $filtr->offset);
        self::assertSame('проект', $filtr->search);
        self::assertTrue($filtr->includeArchived);

        self::assertCount(1, $body['items']);
        self::assertSame('preview', $body['items'][0]['preview']);
        self::assertSame('sess-1', $body['items'][0]['id']);
        self::assertSame(10, $body['limit']);
        self::assertSame(5, $body['offset']);
        self::assertSame(1, $body['total']);
        self::assertFalse($body['hasMore']);
    }

    #[Test]
    public function list_nekorrektnaya_paginaciya_422(): void
    {
        $response = $this->controller->list(
            $this->polzovatel(),
            $this->zapros('GET', '/api/ai/chat/sessions', null, ['limit' => '500']),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('ai.chat.invalid_limit', $this->kodOshibki($response));
    }

    #[Test]
    public function create_i_get_vozvrashchayut_sozdannuyu_sessiyu(): void
    {
        $create = $this->controller->create(
            $this->polzovatel(),
            $this->zapros('POST', '/api/ai/chat/sessions', [
                'credentialId' => 'cred-1',
                'model' => 'gpt-4o-mini',
                'title' => 'Новая сессия',
            ]),
        );

        self::assertSame(Response::HTTP_CREATED, $create->getStatusCode());
        $created = $this->dannye($create);
        self::assertSame('sess-new', $created['id']);
        self::assertSame('Новая сессия', $created['title']);
        self::assertSame(0, $created['messageCount']);

        $get = $this->controller->show('sess-new', $this->polzovatel());

        self::assertSame(Response::HTTP_OK, $get->getStatusCode());
        $detail = $this->dannye($get);
        self::assertSame('sess-new', $detail['session']['id']);
        self::assertSame([], $detail['messages']);
    }

    #[Test]
    public function arhivirovanie_idempotentno(): void
    {
        $this->repository->sessii['sess-1'] = $this->session('sess-1');

        $first = $this->controller->archive('sess-1', $this->polzovatel());
        $second = $this->controller->archive('sess-1', $this->polzovatel());

        self::assertSame(Response::HTTP_OK, $first->getStatusCode());
        self::assertSame(Response::HTTP_OK, $second->getStatusCode());
        self::assertTrue($this->dannye($second)['archived']);
        self::assertSame('manual', $this->dannye($second)['archiveReason']);
        self::assertSame(1, $this->repository->sohraneniy);
    }

    #[Test]
    public function udalenie_arhivnoy_sessii_uspeh(): void
    {
        $this->repository->sessii['sess-1'] = $this->session('sess-1', archivedAt: 100);

        $response = $this->controller->remove('sess-1', $this->polzovatel());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('success', $body['status']);
        self::assertArrayHasKey('data', $body);
        self::assertNull($body['data']);
        self::assertArrayNotHasKey('sess-1', $this->repository->sessii);
    }

    #[Test]
    public function udalenie_ne_arhivnoy_sessii_409(): void
    {
        $this->repository->sessii['sess-1'] = $this->session('sess-1');

        $response = $this->controller->remove('sess-1', $this->polzovatel());

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame('ai.chat.session_not_archived', $this->kodOshibki($response));
    }

    #[Test]
    public function turn_v_arhivnoy_sessii_409(): void
    {
        $this->repository->sessii['sess-1'] = $this->session('sess-1', archivedAt: 100);

        $response = $this->controller->turn(
            'sess-1',
            $this->polzovatel(),
            $this->zapros('POST', '/api/ai/chat/sessions/sess-1/turns', ['message' => 'привет']),
        );

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame('ai.chat.session_archived', $this->kodOshibki($response));
        self::assertSame(0, $this->otpravitel->yzovov);
    }

    #[Test]
    public function turn_odnovremenno_message_i_tool_results_422(): void
    {
        $this->repository->sessii['sess-1'] = $this->session('sess-1');

        $response = $this->controller->turn(
            'sess-1',
            $this->polzovatel(),
            $this->zapros('POST', '/api/ai/chat/sessions/sess-1/turns', [
                'message' => 'привет',
                'toolResults' => [['toolCallId' => 'call-1']],
            ]),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('ai.chat.invalid_payload', $this->kodOshibki($response));
        self::assertSame(0, $this->otpravitel->yzovov);
    }

    #[Test]
    public function turn_bez_message_i_tool_results_422(): void
    {
        $this->repository->sessii['sess-1'] = $this->session('sess-1');

        $response = $this->controller->turn(
            'sess-1',
            $this->polzovatel(),
            $this->zapros('POST', '/api/ai/chat/sessions/sess-1/turns', []),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('ai.chat.invalid_payload', $this->kodOshibki($response));
        self::assertSame(0, $this->otpravitel->yzovov);
    }

    #[Test]
    public function turn_unknown_provider_422(): void
    {
        $this->repository->sessii['sess-1'] = $this->session('sess-1', provider: 'future');
        $this->rekvizity->policyErrors['future'] = 'account.opencode_credential.unknown_provider';

        $response = $this->controller->turn(
            'sess-1',
            $this->polzovatel(),
            $this->zapros('POST', '/api/ai/chat/sessions/sess-1/turns', ['message' => 'привет']),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('ai.chat.provider_not_supported', $this->kodOshibki($response));
        self::assertSame(0, $this->otpravitel->yzovov);
    }

    #[Test]
    public function turn_uspeshno_vozvrashchaet_otvet_assistenta(): void
    {
        $this->repository->sessii['sess-1'] = $this->session('sess-1', title: '');
        $this->otpravitel->otvet = [
            'status' => 200,
            'payload' => ['choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Готово'],
            ]]],
        ];

        $response = $this->controller->turn(
            'sess-1',
            $this->polzovatel(),
            $this->zapros('POST', '/api/ai/chat/sessions/sess-1/turns', ['message' => 'Создай проект']),
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->dannye($response);
        self::assertSame('Готово', $body['assistant']['content']);
        self::assertSame('sess-1', $body['session']['id']);
        self::assertFalse($body['session']['archived']);
        self::assertSame('Создай проект', $body['session']['title']);
        self::assertSame(1, $this->otpravitel->yzovov);
    }

    private function polzovatel(): JWTUser
    {
        return new JWTUser('account-1', 'account-1@example.com', 'hash', ['ROLE_USER']);
    }

    /**
     * @param array<string, mixed>|null $telo
     * @param array<string, string>      $query
     */
    private function zapros(string $method, string $path, ?array $telo = null, array $query = []): Request
    {
        return Request::create(
            $path,
            $method,
            $query,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $telo !== null ? json_encode($telo, JSON_THROW_ON_ERROR) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dannye(JsonResponse $response): array
    {
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('success', $body['status']);
        self::assertArrayHasKey('data', $body);
        self::assertIsArray($body['data']);

        /** @var array<string, mixed> $data */
        $data = $body['data'];

        return $data;
    }

    private function kodOshibki(JsonResponse $response): string
    {
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('fail', $body['status']);

        return (string) $body['data']['code'];
    }

    private function session(
        string $id = 'sess-1',
        string $accountId = 'account-1',
        ?int $archivedAt = null,
        string $provider = 'openai',
        string $title = 'Заголовок',
    ): SessiyaAiChata {
        return SessiyaAiChata::restore(
            $id,
            $accountId,
            $title,
            $provider,
            'cred-1',
            'gpt-4o-mini',
            null,
            null,
            1_900_000_000,
            $archivedAt,
            $archivedAt !== null ? 'manual' : null,
            self::NOW,
            self::NOW,
        );
    }

    private function readModel(string $id = 'sess-1'): SessiyaAiChataReadModel
    {
        return new SessiyaAiChataReadModel(
            $id,
            'Заголовок',
            'openai',
            'gpt-4o-mini',
            false,
            null,
            false,
            'preview',
            self::NOW,
            self::NOW,
            1_900_000_000,
            1,
        );
    }
}
