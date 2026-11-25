<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Business\UseCase;

use App\Account\Business\Model\OpenCode\ProviderSessionTransport;
use App\Account\Business\ReadModel\OpenCodeCredentialSecretReadModel;
use App\Account\Business\ReadModel\ProviderSessionPolicyReadModel;
use App\AiChat\Business\Command\VypolnitHodAiChata;
use App\AiChat\Business\Config\NastroikiAiChata;
use App\AiChat\Business\Model\RolSoobsheniyaAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\Model\SoobshenieAiChata;
use App\AiChat\Business\UseCase\VypolnitHodAiChataUseCase;
use App\AiChat\Infra\Service\UpstreamUnavailableException;
use App\AiChat\Tests\Support\FakeRekvizityDlyaSessiiAiChata;
use App\AiChat\Tests\Support\FixedUuidGenerator;
use App\AiChat\Tests\Support\ImmediateTransactionManager;
use App\AiChat\Tests\Support\InMemorySessiyaAiChataRepository;
use App\AiChat\Tests\Support\InMemorySoobshenieAiChataRepository;
use App\AiChat\Tests\Support\RecordingOtpravitelChatZaprosov;
use App\Utils\Service\TestClockService;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Тесты хода AI-чата: запись истории, ротация provider-сессии, gate и payload.
 */
final class VypolnitHodAiChataUseCaseTest extends TestCase
{
    // --- Сообщение пользователя ---

    #[Test]
    public function soobshenie_sohranyaet_user_i_assistenta_zagolovok_i_aktivnost(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            provider: 'opencode',
            providerSessionId: 'prov-1',
            providerSessionExpiresAt: time() + 1_000,
            title: '',
        );

        $otpravitel->otvet = $this->otvetAssistenta('Привет');

        $before = time();
        $result = $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
            ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'Создай проект'));
        $after = time();

        $session = $repository->sessii['sess-1'];
        $messages = $soobsheniya->vse('sess-1');

        $this->assertCount(2, $messages);
        $this->assertSame(RolSoobsheniyaAiChata::User, $messages[0]->role);
        $this->assertSame('Создай проект', $messages[0]->content);
        $this->assertSame(1, $messages[0]->seq);
        $this->assertSame(RolSoobsheniyaAiChata::Assistant, $messages[1]->role);
        $this->assertSame('Привет', $messages[1]->content);
        $this->assertSame(2, $messages[1]->seq);

        $this->assertSame('Создай проект', $session->title());
        $this->assertSame('prov-1', $session->providerSessionId());
        $this->assertGreaterThanOrEqual($before, $session->updatedAt());
        $this->assertLessThanOrEqual($after, $session->updatedAt());

        $this->assertSame('Привет', $result->content);
        $this->assertSame('sess-1', $result->sessionId);
        $this->assertSame('Создай проект', $result->title);
        $this->assertSame(1, $otpravitel->yzovov);
    }

    // --- Tool-результаты ---

    #[Test]
    public function tool_results_sohranyayut_tool_soobsheniya_i_assistenta_s_tool_calls(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie();

        $soobsheniya->dobavit(
            SoobshenieAiChata::polzovatelya('h1', 'sess-1', 1, 'найди', time()),
            SoobshenieAiChata::assistenta('h2', 'sess-1', 2, null, [[
                'id' => 'call_1',
                'type' => 'function',
                'function' => ['name' => 'search', 'arguments' => '{}'],
            ]], time()),
        );

        $otpravitel->otvet = [
            'status' => 200,
            'payload' => ['choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_2',
                        'type' => 'function',
                        'function' => ['name' => 'post', 'arguments' => '{"a":1}'],
                    ]],
                ],
            ]]],
        ];

        $result = $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
            ->vypolnit(new VypolnitHodAiChata(
                'sess-1',
                toolResults: [[
                    'toolCallId' => 'call_1',
                    'content' => '{"ok":true}',
                    'name' => 'search',
                ]],
            ));

        $messages = $soobsheniya->vse('sess-1');

        $this->assertCount(4, $messages);
        $this->assertSame(RolSoobsheniyaAiChata::Tool, $messages[2]->role);
        $this->assertSame(3, $messages[2]->seq);
        $this->assertSame('call_1', $messages[2]->toolCallId);
        $this->assertSame('{"ok":true}', $messages[2]->content);
        $this->assertSame('search', $messages[2]->name);
        $this->assertSame(RolSoobsheniyaAiChata::Assistant, $messages[3]->role);
        $this->assertSame(4, $messages[3]->seq);

        $this->assertSame(
            [['id' => 'call_2', 'name' => 'post', 'arguments' => '{"a":1}']],
            $result->toolCalls,
        );
        $this->assertNull($result->content);

        // В upstream уходит последний tool-результат с tool_call_id.
        $this->assertSame('tool', $otpravitel->poslednie['messages'][2]['role']);
        $this->assertSame('call_1', $otpravitel->poslednie['messages'][2]['tool_call_id']);
    }

    // --- Истечение и архив ---

    #[Test]
    public function istekshaya_sessiya_arhiviruetsya_i_daet_409_bez_proksi(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            expiresAt: time() - 10,
            archivedAt: null,
        );

        try {
            $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
                ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));
            $this->fail('Ожидалась ошибка ai.chat.session_expired');
        } catch (\DomainException $exception) {
            $this->assertSame('ai.chat.session_expired', $exception->getMessage());
        }

        $session = $repository->sessii['sess-1'];
        $this->assertTrue($session->arhivirovana());
        $this->assertSame('expired', $session->archiveReason());
        $this->assertSame(1, $repository->sohraneniy);
        $this->assertSame(0, $otpravitel->yzovov);
        $this->assertSame(0, $soobsheniya->kolichestvo('sess-1'));
    }

    #[Test]
    public function arhivnaya_sessiya_daet_409_archived_bez_proksi(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            archivedAt: 100,
            archiveReason: 'manual',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.session_archived');

        $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
            ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));
    }

    // --- Gate провайдера ---

    #[Test]
    public function provayder_bez_supports_ai_chat_daet_422_bez_proksi(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            supportsAiChat: false,
        );

        try {
            $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
                ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));
            $this->fail('Ожидалась ошибка ai.chat.provider_not_supported');
        } catch (\DomainException $exception) {
            $this->assertSame('ai.chat.provider_not_supported', $exception->getMessage());
        }

        $this->assertSame(0, $otpravitel->yzovov);
    }

    // --- Реквизиты ---

    #[Test]
    public function otsutstvie_credential_id_daet_credential_deleted(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            credentialId: null,
        );

        try {
            $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
                ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));
            $this->fail('Ожидалась ошибка ai.chat.credential_deleted');
        } catch (\DomainException $exception) {
            $this->assertSame('ai.chat.credential_deleted', $exception->getMessage());
        }

        $this->assertSame(0, $repository->sohraneniy);
        $this->assertSame(0, $otpravitel->yzovov);
    }

    #[Test]
    public function udalennye_rekvizity_otvyazyvayut_i_arhiviruyut_sessiyu(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            bezRekvizitov: true,
        );

        try {
            $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
                ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));
            $this->fail('Ожидалась ошибка ai.chat.credential_deleted');
        } catch (\DomainException $exception) {
            $this->assertSame('ai.chat.credential_deleted', $exception->getMessage());
        }

        $session = $repository->sessii['sess-1'];
        $this->assertNull($session->credentialId());
        $this->assertTrue($session->arhivirovana());
        $this->assertSame('credential_deleted', $session->archiveReason());
        $this->assertSame(1, $repository->sohraneniy);
        $this->assertSame(0, $otpravitel->yzovov);
    }

    #[Test]
    public function nedostupnaya_model_daet_oshibku_account(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.model_not_available');

        $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
            ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет', model: 'unknown-model'));
    }

    // --- Ротация provider-сессии ---

    #[Test]
    public function proslrochennaya_provider_sessiya_rotiruetsya_i_sohranyaetsya(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            provider: 'opencode',
            providerSessionId: 'old-prov',
            providerSessionExpiresAt: time() - 10,
            transport: 'header',
            key: 'x-opencode-session',
            ttlSeconds: 300,
        );
        $otpravitel->otvet = $this->otvetAssistenta('ok');

        $before = time();
        $this->useCase(
            $repository,
            $soobsheniya,
            $rekvizity,
            $otpravitel,
            new FixedUuidGenerator('prov-new'),
        )->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));
        $after = time();

        $session = $repository->sessii['sess-1'];

        $this->assertSame('prov-new', $session->providerSessionId());
        $this->assertGreaterThanOrEqual($before + 300, (int) $session->providerSessionExpiresAt());
        $this->assertLessThanOrEqual($after + 300, (int) $session->providerSessionExpiresAt());
        $this->assertSame('prov-new', $otpravitel->poslednie['sessionId']);
    }

    #[Test]
    public function svezhaya_provider_sessiya_ne_menyaetsya(): void
    {
        $providerExpiresAt = time() + 1_000;

        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            provider: 'opencode',
            providerSessionId: 'prov-1',
            providerSessionExpiresAt: $providerExpiresAt,
            transport: 'header',
            key: 'x-opencode-session',
            ttlSeconds: 300,
        );
        $otpravitel->otvet = $this->otvetAssistenta('ok');

        $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
            ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));

        $session = $repository->sessii['sess-1'];
        $this->assertSame('prov-1', $session->providerSessionId());
        $this->assertSame($providerExpiresAt, $session->providerSessionExpiresAt());
        $this->assertSame('prov-1', $otpravitel->poslednie['sessionId']);
    }

    #[Test]
    public function transport_none_ne_sozdaet_provider_sessiyu(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            provider: 'openai',
            transport: 'none',
        );
        $otpravitel->otvet = $this->otvetAssistenta('ok');

        $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
            ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));

        $session = $repository->sessii['sess-1'];
        $this->assertNull($session->providerSessionId());
        $this->assertNull($session->providerSessionExpiresAt());
        $this->assertNull($otpravitel->poslednie['sessionId']);
    }

    // --- Контекст ---

    #[Test]
    public function v_proksi_uhodit_tolko_poslednie_n_soobshenii_plyus_novye(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie();
        $otpravitel->otvet = $this->otvetAssistenta('ok');

        foreach ([1, 2, 3, 4] as $seq) {
            $soobsheniya->dobavit(
                SoobshenieAiChata::polzovatelya('h'.$seq, 'sess-1', $seq, 'msg'.$seq, time()),
            );
        }

        $this->useCase(
            $repository,
            $soobsheniya,
            $rekvizity,
            $otpravitel,
            new FixedUuidGenerator(),
            maxContextMessages: 2,
        )->vypolnit(new VypolnitHodAiChata('sess-1', message: 'new'));

        $messages = $otpravitel->poslednie['messages'];

        $this->assertCount(3, $messages);
        $this->assertSame('msg3', $messages[0]['content']);
        $this->assertSame('msg4', $messages[1]['content']);
        $this->assertSame('new', $messages[2]['content']);
    }

    // --- Лимит сообщений ---

    #[Test]
    public function limit_500_daet_409_i_ne_dobavlyaet_soobsheniya(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie();
        $otpravitel->otvet = $this->otvetAssistenta('ok');

        for ($seq = 1; $seq <= SessiyaAiChata::MAX_MESSAGES; ++$seq) {
            $soobsheniya->dobavit(
                SoobshenieAiChata::polzovatelya('h'.$seq, 'sess-1', $seq, 'm'.$seq, time()),
            );
        }

        try {
            $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
                ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'лишнее'));
            $this->fail('Ожидалась ошибка ai.chat.session_message_limit');
        } catch (\DomainException $exception) {
            $this->assertSame('ai.chat.session_message_limit', $exception->getMessage());
        }

        $this->assertSame(SessiyaAiChata::MAX_MESSAGES, $soobsheniya->kolichestvo('sess-1'));
    }

    // --- Валидация payload ---

    /**
     * @return iterable<string, array{message: ?string, toolResults: list<array<string, mixed>>, tools: list<array<string, mixed>>}>
     */
    public static function nevalidnyePayload(): iterable
    {
        yield 'оба источника' => [
            'message' => 'x',
            'toolResults' => [['toolCallId' => 'call_1']],
            'tools' => [],
        ];
        yield 'ни одного источника' => [
            'message' => null,
            'toolResults' => [],
            'tools' => [],
        ];
        yield 'пустое сообщение' => [
            'message' => '   ',
            'toolResults' => [],
            'tools' => [],
        ];
        yield 'слишком длинное сообщение' => [
            'message' => str_repeat('a', 100_001),
            'toolResults' => [],
            'tools' => [],
        ];
        yield 'пустой toolCallId' => [
            'message' => null,
            'toolResults' => [['toolCallId' => '']],
            'tools' => [],
        ];
        yield 'больше 64 инструментов' => [
            'message' => 'x',
            'toolResults' => [],
            'tools' => array_fill(0, 65, ['type' => 'function', 'function' => ['name' => 'ok']]),
        ];
        yield 'плохое имя инструмента' => [
            'message' => 'x',
            'toolResults' => [],
            'tools' => [['type' => 'function', 'function' => ['name' => 'bad name']]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $toolResults
     * @param list<array<string, mixed>> $tools
     */
    #[Test]
    #[DataProvider('nevalidnyePayload')]
    public function nevalidnyi_payload_daet_422_bez_proksi(
        ?string $message,
        array $toolResults,
        array $tools,
    ): void {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie();

        try {
            $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
                ->vypolnit(new VypolnitHodAiChata('sess-1', $message, $toolResults, $tools));
            $this->fail('Ожидалась ошибка ai.chat.invalid_payload');
        } catch (\DomainException $exception) {
            $this->assertSame('ai.chat.invalid_payload', $exception->getMessage());
        }

        $this->assertSame(0, $otpravitel->yzovov);
        $this->assertSame(0, $soobsheniya->kolichestvo('sess-1'));
    }

    // --- Владелец ---

    #[Test]
    public function chuzhaya_sessiya_daet_404(): void
    {
        // Текущий пользователь — account-1, а сессия принадлежит account-2:
        // чужая сессия должна быть неотличима от несуществующей.
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie(
            accountId: 'account-1',
            sessionAccountId: 'account-2',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.session_not_found');

        $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
            ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));
    }

    // --- Ошибка upstream ---

    #[Test]
    public function oshibka_upstream_nichego_ne_pishet(): void
    {
        [$repository, $soobsheniya, $rekvizity, $otpravitel] = $this->okruzhenie();
        $otpravitel->oshibka = new UpstreamUnavailableException();

        try {
            $this->useCase($repository, $soobsheniya, $rekvizity, $otpravitel)
                ->vypolnit(new VypolnitHodAiChata('sess-1', message: 'привет'));
            $this->fail('Ожидалось upstream-исключение');
        } catch (UpstreamUnavailableException) {
            // ожидаемо
        }

        $this->assertSame(0, $soobsheniya->kolichestvo('sess-1'));
        $this->assertSame(0, $repository->sohraneniy);
    }

    // --- Фабрики ---

    /**
     * Собирает окружение с одной валидной сессией, реквизитами и политикой.
     *
     * @return array{0: InMemorySessiyaAiChataRepository, 1: InMemorySoobshenieAiChataRepository, 2: FakeRekvizityDlyaSessiiAiChata, 3: RecordingOtpravitelChatZaprosov}
     */
    private function okruzhenie(
        string $accountId = 'account-1',
        string $provider = 'openai',
        ?string $credentialId = 'cred-1',
        string $title = 'Заголовок',
        ?int $expiresAt = null,
        ?int $archivedAt = null,
        ?string $archiveReason = null,
        ?string $providerSessionId = null,
        ?int $providerSessionExpiresAt = null,
        string $transport = 'none',
        ?string $key = null,
        int $ttlSeconds = 300,
        bool $supportsAiChat = true,
        bool $bezRekvizitov = false,
        ?string $sessionAccountId = null,
    ): array {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = SessiyaAiChata::restore(
            'sess-1',
            $sessionAccountId ?? $accountId,
            $title,
            $provider,
            $credentialId,
            'gpt-4o-mini',
            $providerSessionId,
            $providerSessionExpiresAt,
            $expiresAt ?? time() + 86_400,
            $archivedAt,
            $archiveReason,
            1_000_000,
            1_000_000,
        );

        $soobsheniya = new InMemorySoobshenieAiChataRepository();

        $rekvizity = new FakeRekvizityDlyaSessiiAiChata($accountId);

        if (!$bezRekvizitov) {
            $rekvizity->credentials['cred-1'] = $this->credential('cred-1', $provider);
        }

        $rekvizity->policies[$provider] = new ProviderSessionPolicyReadModel(
            $provider,
            $transport,
            $key,
            $ttlSeconds,
            $supportsAiChat,
        );

        return [$repository, $soobsheniya, $rekvizity, new RecordingOtpravitelChatZaprosov()];
    }

    private function useCase(
        InMemorySessiyaAiChataRepository $repository,
        InMemorySoobshenieAiChataRepository $soobsheniya,
        FakeRekvizityDlyaSessiiAiChata $rekvizity,
        RecordingOtpravitelChatZaprosov $otpravitel,
        ?FixedUuidGenerator $uuidGenerator = null,
        int $maxContextMessages = 100,
        ?ClockInterface $clock = null,
    ): VypolnitHodAiChataUseCase {
        return new VypolnitHodAiChataUseCase(
            $repository,
            $soobsheniya,
            $rekvizity,
            $uuidGenerator ?? new FixedUuidGenerator(),
            new NastroikiAiChata(86_400, 300, $maxContextMessages),
            new ImmediateTransactionManager(),
            $otpravitel,
            'https://fallback.example.test',
            $clock ?? new TestClockService(),
        );
    }

    private function credential(
        string $id = 'cred-1',
        string $provider = 'openai',
        string $model = 'gpt-4o-mini',
    ): OpenCodeCredentialSecretReadModel {
        return new OpenCodeCredentialSecretReadModel(
            $id,
            'account-1',
            $provider,
            'https://api.example.test',
            [$model],
            $model,
            $model,
            'secret-key',
        );
    }

    /**
     * @return array{status: int, payload: array<array-key, mixed>}
     */
    private function otvetAssistenta(string $content): array
    {
        return [
            'status' => 200,
            'payload' => ['choices' => [[
                'message' => ['role' => 'assistant', 'content' => $content],
            ]]],
        ];
    }
}
