<?php

declare(strict_types=1);

namespace App\AiChat\Business\UseCase;

use App\AiChat\Business\Command\SozdatSessiyuAiChata;
use App\AiChat\Business\Config\NastroikiAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\Port\RekvizityDlyaSessiiAiChata;
use App\AiChat\Business\Port\SessiyaAiChataRepository;
use App\AiChat\Business\Port\UuidGenerator;
use App\AiChat\Business\ReadModel\SessiyaAiChataReadModel;
use Psr\Clock\ClockInterface;

/**
 * Создание сессии AI-чата на реквизитах текущего пользователя.
 *
 * Если реквизиты не выбраны, берётся первый реквизит аккаунта. Провайдер
 * обязан поддерживать AI-чат (иначе `ai.chat.provider_not_supported`), а
 * provider-сессия создаётся только для транспорта, отличного от `none`.
 */
final class SozdatSessiyuAiChataUseCase
{
    public function __construct(
        private readonly SessiyaAiChataRepository $repository,
        private readonly RekvizityDlyaSessiiAiChata $rekvizity,
        private readonly UuidGenerator $uuidGenerator,
        private readonly NastroikiAiChata $nastroiki,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException `ai.chat.credential_required`,
     *                          `ai.chat.provider_not_supported`;
     *                          ошибки Account о недоступной модели пробрасываются как есть
     */
    public function vypolnit(SozdatSessiyuAiChata $komanda): SessiyaAiChataReadModel
    {
        $now = $this->clock->now()->getTimestamp();
        $accountId = $this->rekvizity->tekuschiiAccountId();

        $credentialId = $this->razreshitCredentialId($accountId, $komanda->credentialId);

        $credential = $this->rekvizity->poluchitRekvizity($accountId, $credentialId, $komanda->model);

        $provider = trim($credential->provider);

        if ($provider === '') {
            throw new \DomainException('ai.chat.provider_not_supported');
        }

        $policy = $this->poluchitPolitiku($provider);

        if (!$policy->supportsAiChat) {
            throw new \DomainException('ai.chat.provider_not_supported');
        }

        $session = SessiyaAiChata::create(
            $this->uuidGenerator->generit(),
            $accountId,
            $provider,
            $credentialId,
            $credential->model,
            $now,
            $policy->vPolitiku(),
            $this->nastroiki->chatSessionTtl,
            $this->nastroiki->providerSessionMaxTtl,
            $this->uuidGenerator->generit(),
        );

        $title = $komanda->title !== null ? trim($komanda->title) : '';

        if ($title !== '') {
            $session->naznachitZagolovokIzPervogoSoobsheniya($title, $now);
        }

        $this->repository->sohranit($session);

        return SessiyaAiChataReadModel::izDomena($session);
    }

    /**
     * @throws \DomainException `ai.chat.credential_required`, если реквизитов нет
     */
    private function razreshitCredentialId(string $accountId, ?string $credentialId): string
    {
        $credentialId = $credentialId !== null ? trim($credentialId) : '';

        if ($credentialId !== '') {
            return $credentialId;
        }

        $existing = $this->rekvizity->identifikatoryRekvizitov($accountId);

        if ($existing === []) {
            throw new \DomainException('ai.chat.credential_required');
        }

        return $existing[0];
    }

    /**
     * Политика провайдера с приведением ошибок Account к ключу AI-чата.
     *
     * @throws \DomainException `ai.chat.provider_not_supported`
     */
    private function poluchitPolitiku(string $provider): \App\Account\Business\ReadModel\ProviderSessionPolicyReadModel
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
}
