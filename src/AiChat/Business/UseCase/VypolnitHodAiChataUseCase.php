<?php

declare(strict_types=1);

namespace App\AiChat\Business\UseCase;

use App\Account\Business\Model\OpenCode\ProviderSessionTransport;
use App\Account\Business\ReadModel\OpenCodeCredentialSecretReadModel;
use App\Account\Business\ReadModel\ProviderSessionPolicyReadModel;
use App\AiChat\Business\Command\VypolnitHodAiChata;
use App\AiChat\Business\Config\NastroikiAiChata;
use App\AiChat\Business\Model\RolSoobsheniyaAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\Model\SoobshenieAiChata;
use App\AiChat\Business\Port\OtpravitelChatZaprosov;
use App\AiChat\Business\Port\RekvizityDlyaSessiiAiChata;
use App\AiChat\Business\Port\SessiyaAiChataRepository;
use App\AiChat\Business\Port\SoobshenieAiChataRepository;
use App\AiChat\Business\Port\TransactionManager;
use App\AiChat\Business\Port\UuidGenerator;
use App\AiChat\Business\ReadModel\RezultatHodaAiChataReadModel;
use App\AiChat\Business\ReadModel\SoobshenieAiChataReadModel;
use App\AiChat\Infra\Service\UpstreamInvalidResponseException;
use App\AiChat\Infra\Service\UpstreamUnavailableException;
use Psr\Clock\ClockInterface;

/**
 * Один ход диалога AI-чата: сообщение пользователя или результаты tool-вызовов.
 *
 * Границы работы:
 * - всё, что можно проверить до upstream (владелец, архив/истечение, политика,
 *   реквизиты, payload), проверяется до сетевого вызова;
 * - upstream вызывается ВНЕ транзакции; до его ответа ничего не пишется в БД;
 * - ротация provider-сессии вычисляется до вызова (чтобы передать новый id в
 *   upstream), но сохраняется только в транзакции записи истории;
 * - в транзакции повторно проверяются архив/истечение и лимит сообщений,
 *   затем атомарно добавляются входящие сообщения и ответ ассистента.
 */
final class VypolnitHodAiChataUseCase
{
    /** Максимальная длина одного пользовательского сообщения. */
    private const MAX_MESSAGE_LENGTH = 100_000;

    /** Максимальное количество инструментов в одном запросе. */
    private const MAX_TOOLS = 64;

    public function __construct(
        private readonly SessiyaAiChataRepository $repository,
        private readonly SoobshenieAiChataRepository $soobsheniya,
        private readonly RekvizityDlyaSessiiAiChata $rekvizity,
        private readonly UuidGenerator $uuidGenerator,
        private readonly NastroikiAiChata $nastroiki,
        private readonly TransactionManager $transactionManager,
        private readonly OtpravitelChatZaprosov $otpravitel,
        private readonly string $openCodeApiUrl,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException `ai.chat.session_not_found`,
     *                          `ai.chat.session_expired`,
     *                          `ai.chat.session_archived`,
     *                          `ai.chat.provider_not_supported`,
     *                          `ai.chat.credential_deleted`,
     *                          `ai.chat.invalid_payload`,
     *                          `ai.chat.session_message_limit`;
     *                          ошибка Account о недоступной модели пробрасывается как есть;
     *                          upstream-исключения прокси пробрасываются
     */
    public function vypolnit(VypolnitHodAiChata $komanda): RezultatHodaAiChataReadModel
    {
        $now = $this->clock->now()->getTimestamp();
        $accountId = $this->rekvizity->tekuschiiAccountId();

        $session = $this->repository->naytiPoId($komanda->sessionId);

        if ($session === null) {
            throw new \DomainException('ai.chat.session_not_found');
        }

        $session->trebovatVladelca($accountId);

        if ($session->arhivirovatPriIstechenii($now)) {
            $this->repository->sohranit($session);

            throw new \DomainException('ai.chat.session_expired');
        }

        if ($session->arhivirovana()) {
            throw new \DomainException('ai.chat.session_archived');
        }

        $policy = $this->poluchitPolitiku($session->provider());

        if (!$policy->supportsAiChat) {
            throw new \DomainException('ai.chat.provider_not_supported');
        }

        $domainPolicy = $policy->vPolitiku();
        $credential = $this->poluchitRekvizity($session, $now, $komanda->model);
        $model = $this->razreshitModel($komanda->model, $session->model());
        $vhodnye = $this->validirovatPayload($komanda);

        $istoriya = $this->soobsheniya->poslednie($session->id(), $this->nastroiki->maxContextMessages);
        $messages = array_map(
            static fn (SoobshenieAiChata $soobshenie): array => $soobshenie->vFormatOpenAi(),
            $istoriya,
        );

        foreach ($vhodnye as $vhod) {
            $messages[] = $this->vFormatOpenAiVhoda($vhod);
        }

        $tekstDlyaZagolovka = $this->najtiTekstZagolovka($istoriya, $komanda);

        [$rotatedProviderSessionId, $rotatedProviderSessionExpiresAt] = $this->rotacia(
            $session,
            $domainPolicy,
            $now,
        );

        $providerSessionId = $domainPolicy->transport === ProviderSessionTransport::None
            ? null
            : $session->providerSessionId();

        $otvet = $this->otpravitel->otpravit(
            $this->bazovyyUrl($credential),
            $credential->apiKey,
            $model,
            $messages,
            $komanda->tools,
            $credential->provider,
            $domainPolicy,
            $providerSessionId,
        );

        [$assistantContent, $assistantToolCalls] = $this->razobratOtvet($otvet);

        /** @var array{0: SessiyaAiChata, 1: SoobshenieAiChata} $rezultat */
        $rezultat = $this->transactionManager->vTransakcii(
            fn (): array => $this->zapisatHod(
                $session->id(),
                $accountId,
                $now,
                $vhodnye,
                $assistantContent,
                $assistantToolCalls,
                $tekstDlyaZagolovka,
                $rotatedProviderSessionId,
                $rotatedProviderSessionExpiresAt,
            ),
        );

        [$sessiya, $assistant] = $rezultat;

        return new RezultatHodaAiChataReadModel(
            $assistant->content,
            SoobshenieAiChataReadModel::ploskieToolCalls($assistant->toolCalls),
            $sessiya->id(),
            $sessiya->providerSessionExpiresAt(),
            $sessiya->arhivirovana(),
            $sessiya->updatedAt(),
            $sessiya->title(),
        );
    }

    /**
     * Политика провайдера с приведением ошибок Account к ключу AI-чата.
     */
    private function poluchitPolitiku(string $provider): ProviderSessionPolicyReadModel
    {
        try {
            return $this->rekvizity->poluchitPolitiku($provider);
        } catch (\DomainException $exception) {
            if (\in_array($exception->getMessage(), [
                'account.opencode_credential.empty_provider',
                'account.opencode_credential.unknown_provider',
            ], true)) {
                throw new \DomainException('ai.chat.provider_not_supported');
            }

            throw $exception;
        }
    }

    /**
     * Реквизиты владельца. Удалённые (или чужие) реквизиты отвязываются от
     * сессии и архивируют её с причиной `credential_deleted`.
     */
    private function poluchitRekvizity(
        SessiyaAiChata $session,
        int $now,
        ?string $model,
    ): OpenCodeCredentialSecretReadModel {
        $credentialId = $session->credentialId();

        if ($credentialId === null || trim($credentialId) === '') {
            throw new \DomainException('ai.chat.credential_deleted');
        }

        try {
            return $this->rekvizity->poluchitRekvizity($session->accountId(), $credentialId, $model);
        } catch (\DomainException $exception) {
            if ($exception->getMessage() === 'account.opencode_credential.not_found') {
                $session->otvyazatRekvizity($now);
                $this->repository->sohranit($session);

                throw new \DomainException('ai.chat.credential_deleted');
            }

            throw $exception;
        }
    }

    /**
     * Модель хода: явно переданная в команде или модель сессии.
     */
    private function razreshitModel(?string $modelKomandy, string $modelSessii): string
    {
        $model = $modelKomandy !== null ? trim($modelKomandy) : '';

        return $model !== '' ? $model : $modelSessii;
    }

    /**
     * Проверка payload и построение входящих сообщений.
     *
     * Ровно одно из `message` / `toolResults`. `message` — непустая строка не
     * длиннее 100000 символов. `toolResults` — непустой список с непустым
     * `toolCallId`. `tools` — не больше 64 штук с именами
     * `^[A-Za-z0-9_-]{1,64}$`.
     *
     * @return list<array{role: string, content: ?string, toolCallId?: string, name?: ?string}>
     */
    private function validirovatPayload(VypolnitHodAiChata $komanda): array
    {
        $hasMessage = $komanda->message !== null;

        if ($hasMessage && (
            trim($komanda->message) === ''
            || mb_strlen($komanda->message) > self::MAX_MESSAGE_LENGTH
        )) {
            throw new \DomainException('ai.chat.invalid_payload');
        }

        $toolResults = $this->validirovatToolResults($komanda->toolResults);
        $hasToolResults = $toolResults !== [];

        if ($hasMessage === $hasToolResults) {
            throw new \DomainException('ai.chat.invalid_payload');
        }

        $this->validirovatTools($komanda->tools);

        if ($hasMessage) {
            return [[
                'role' => RolSoobsheniyaAiChata::User->value,
                'content' => $komanda->message,
            ]];
        }

        return $toolResults;
    }

    /**
     * @param array<array-key, mixed> $toolResults
     *
     * @return list<array{role: string, content: ?string, toolCallId: string, name: ?string}>
     */
    private function validirovatToolResults(array $toolResults): array
    {
        if ($toolResults === []) {
            return [];
        }

        if (!array_is_list($toolResults)) {
            throw new \DomainException('ai.chat.invalid_payload');
        }

        $rezultat = [];

        foreach ($toolResults as $item) {
            if (!\is_array($item)) {
                throw new \DomainException('ai.chat.invalid_payload');
            }

            $toolCallId = $item['toolCallId'] ?? null;

            if (!\is_string($toolCallId) || trim($toolCallId) === '') {
                throw new \DomainException('ai.chat.invalid_payload');
            }

            $content = $item['content'] ?? null;

            if ($content !== null && !\is_string($content)) {
                throw new \DomainException('ai.chat.invalid_payload');
            }

            $name = $item['name'] ?? null;

            if ($name !== null && !\is_string($name)) {
                throw new \DomainException('ai.chat.invalid_payload');
            }

            $rezultat[] = [
                'role' => RolSoobsheniyaAiChata::Tool->value,
                'content' => $content,
                'toolCallId' => $toolCallId,
                'name' => $name,
            ];
        }

        return $rezultat;
    }

    /**
     * @param array<array-key, mixed> $tools
     */
    private function validirovatTools(array $tools): void
    {
        if (\count($tools) > self::MAX_TOOLS) {
            throw new \DomainException('ai.chat.invalid_payload');
        }

        if ($tools !== [] && !array_is_list($tools)) {
            throw new \DomainException('ai.chat.invalid_payload');
        }

        foreach ($tools as $tool) {
            if (!\is_array($tool)) {
                throw new \DomainException('ai.chat.invalid_payload');
            }

            $function = $tool['function'] ?? null;

            if (!\is_array($function)) {
                throw new \DomainException('ai.chat.invalid_payload');
            }

            $name = $function['name'] ?? null;

            if (!\is_string($name) || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name) !== 1) {
                throw new \DomainException('ai.chat.invalid_payload');
            }
        }
    }

    /**
     * Текст для заголовка: первое пользовательское сообщение в истории, а для
     * первого хода — текущее сообщение.
     *
     * @param list<SoobshenieAiChata> $istoriya
     */
    private function najtiTekstZagolovka(array $istoriya, VypolnitHodAiChata $komanda): ?string
    {
        foreach ($istoriya as $soobshenie) {
            if (
                $soobshenie->role === RolSoobsheniyaAiChata::User
                && $soobshenie->content !== null
                && trim($soobshenie->content) !== ''
            ) {
                return $soobshenie->content;
            }
        }

        if ($komanda->message !== null && trim($komanda->message) !== '') {
            return $komanda->message;
        }

        return null;
    }

    /**
     * Ротация provider-сессии по её TTL.
     *
     * Новый идентификатор нужен, если транспорта нет у провайдера, сессия
     * ещё не создана или уже истекла. Свежая provider-сессия не меняется.
     *
     * @return array{0: ?string, 1: ?int} новый id и срок жизни provider-сессии
     */
    private function rotacia(
        SessiyaAiChata $session,
        \App\Account\Business\Model\OpenCode\ProviderSessionPolicy $policy,
        int $now,
    ): array {
        if ($policy->transport === ProviderSessionTransport::None) {
            return [null, null];
        }

        if ($session->providerSessionId() !== null && !$session->providerSessionIstekla($now)) {
            return [null, null];
        }

        $providerSessionId = $this->uuidGenerator->generit();
        $expiresAt = $now + min($policy->ttlSeconds, $this->nastroiki->providerSessionMaxTtl);

        $session->aktualizirovatProviderSession($providerSessionId, $expiresAt);

        return [$providerSessionId, $expiresAt];
    }

    /**
     * Разбор ответа upstream: `choices[0].message`.
     *
     * @param array{status: int, payload: array<array-key, mixed>} $otvet
     *
     * @return array{0: ?string, 1: list<array<string, mixed>>}
     */
    private function razobratOtvet(array $otvet): array
    {
        if ($otvet['status'] >= 400) {
            throw new UpstreamUnavailableException();
        }

        $choices = $otvet['payload']['choices'] ?? null;

        if (!\is_array($choices) || !isset($choices[0]) || !\is_array($choices[0])) {
            throw new UpstreamInvalidResponseException();
        }

        $message = $choices[0]['message'] ?? null;

        if (!\is_array($message)) {
            throw new UpstreamInvalidResponseException();
        }

        $content = $message['content'] ?? null;

        if ($content !== null && !\is_string($content)) {
            $content = null;
        }

        $rawToolCalls = $message['tool_calls'] ?? [];

        if (!\is_array($rawToolCalls)) {
            $rawToolCalls = [];
        }

        $toolCalls = array_values(array_filter(
            $rawToolCalls,
            static fn (mixed $call): bool => \is_array($call),
        ));

        return [$content, $toolCalls];
    }

    /**
     * @param array{role: string, content: ?string, toolCallId?: string, name?: ?string} $vhod
     *
     * @return array<string, mixed>
     */
    private function vFormatOpenAiVhoda(array $vhod): array
    {
        $message = [
            'role' => $vhod['role'],
            'content' => $vhod['content'],
        ];

        if (isset($vhod['toolCallId'])) {
            $message['tool_call_id'] = $vhod['toolCallId'];
        }

        $name = $vhod['name'] ?? null;

        if ($name !== null) {
            $message['name'] = $name;
        }

        return $message;
    }

    private function bazovyyUrl(OpenCodeCredentialSecretReadModel $credential): string
    {
        $url = trim($credential->url);

        return $url !== '' ? $url : trim($this->openCodeApiUrl);
    }

    /**
     * Транзакционная запись истории.
     *
     * Внутри повторно загружается сессия с блокировкой, проверяются архив,
     * истечение и лимит сообщений, применяется ротация provider-сессии и
     * атомарно добавляются входящие сообщения и ответ ассистента.
     *
     * @param list<array{role: string, content: ?string, toolCallId?: string, name?: ?string}> $vhodnye
     * @param list<array<string, mixed>>                                                       $assistantToolCalls
     *
     * @return array{0: SessiyaAiChata, 1: SoobshenieAiChata}
     */
    private function zapisatHod(
        string $sessionId,
        string $accountId,
        int $now,
        array $vhodnye,
        ?string $assistantContent,
        array $assistantToolCalls,
        ?string $tekstDlyaZagolovka,
        ?string $rotatedProviderSessionId,
        ?int $rotatedProviderSessionExpiresAt,
    ): array {
        $sessiya = $this->repository->naytiPoIdDlyaObnovleniya($sessionId);

        if ($sessiya === null) {
            throw new \DomainException('ai.chat.session_not_found');
        }

        $sessiya->trebovatVladelca($accountId);

        if ($sessiya->arhivirovatPriIstechenii($now)) {
            $this->repository->sohranit($sessiya);

            throw new \DomainException('ai.chat.session_expired');
        }

        if ($sessiya->arhivirovana()) {
            throw new \DomainException('ai.chat.session_archived');
        }

        $kolichestvo = $this->soobsheniya->kolichestvo($sessiya->id());

        if ($kolichestvo >= SessiyaAiChata::MAX_MESSAGES) {
            throw new \DomainException('ai.chat.session_message_limit');
        }

        if ($rotatedProviderSessionId !== null) {
            $sessiya->aktualizirovatProviderSession($rotatedProviderSessionId, $rotatedProviderSessionExpiresAt);
        }

        $seq = $kolichestvo;
        $novye = [];

        foreach ($vhodnye as $vhod) {
            ++$seq;
            $id = $this->uuidGenerator->generit();

            if ($vhod['role'] === RolSoobsheniyaAiChata::User->value) {
                $novye[] = SoobshenieAiChata::polzovatelya(
                    $id,
                    $sessiya->id(),
                    $seq,
                    (string) $vhod['content'],
                    $now,
                );

                continue;
            }

            $novye[] = SoobshenieAiChata::instrumenta(
                $id,
                $sessiya->id(),
                $seq,
                $vhod['toolCallId'] ?? '',
                $vhod['content'] ?? '',
                $vhod['name'] ?? null,
                $now,
            );
        }

        ++$seq;
        $assistant = SoobshenieAiChata::assistenta(
            $this->uuidGenerator->generit(),
            $sessiya->id(),
            $seq,
            $assistantContent,
            $assistantToolCalls,
            $now,
        );

        $novye[] = $assistant;

        if ($sessiya->title() === '' && $tekstDlyaZagolovka !== null) {
            $sessiya->naznachitZagolovokIzPervogoSoobsheniya($tekstDlyaZagolovka, $now);
        }

        $sessiya->obnovitAktivnost($now);

        $this->soobsheniya->dobavit(...$novye);
        $this->repository->sohranit($sessiya);

        return [$sessiya, $assistant];
    }
}
