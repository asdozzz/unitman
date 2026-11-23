<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase\OpenCode;

use App\Account\Business\Command\IzmenitRekvizityOpenCode;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Model\OpenCode\ProvayderOpenCode;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UmeetShifrovatSekrety;
use App\Account\Business\Utils\PodgotovitelRekvizitovOpenCode;

/**
 * Изменение реквизитов OpenCode текущего пользователя.
 *
 * Ключ перешифровывается только если он действительно передан: пустая строка
 * и `null` означают «оставить прежний». Набор моделей заменяется только если
 * он передан; legacy-поле `model` трактуется как одноэлементный набор.
 */
final class IzmenitRekvizityOpenCodeUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private OpenCodeCredentialRepository $credentialRepository,
        private UmeetShifrovatSekrety $umeetShifrovatSekrety,
    ) {
    }

    public function handle(IzmenitRekvizityOpenCode $command): void
    {
        $accountId = $this->securityService->getCurrentUserId();

        if (trim($command->id) === '') {
            throw new \DomainException('account.opencode_credential.empty_id');
        }

        $credential = $this->credentialRepository->getById(trim($command->id));
        $credential->trebovatVladelca($accountId);

        if ($command->name !== null) {
            $this->trebovatSvobodnoeImya($accountId, $command->name, $credential->getId());
        }

        $apiKeyCipher = null;
        $apiKeyHint = null;

        if ($command->apiKey !== null && $command->apiKey !== '') {
            $apiKeyCipher = $this->umeetShifrovatSekrety->zashifrovat(
                $command->apiKey,
                OpenCodeCredential::makeAad($accountId, $credential->getId()),
            );
            $apiKeyHint = OpenCodeCredential::makeHint($command->apiKey);
        }

        $provider = $command->provider;
        if ($provider !== null) {
            $provider = ProvayderOpenCode::izKoda($provider)->value;
        }

        $url = $command->url;
        if ($url !== null && trim($url) === '') {
            $effective = ProvayderOpenCode::from($provider ?? $credential->getProvider());
            $url = PodgotovitelRekvizitovOpenCode::urlDlyaProvaydera($effective, '');
        }

        [$models, $defaultModel] = $this->razreshitModeli($command, $credential);

        $credential->izmenit(
            $command->name,
            $provider,
            $url,
            $models,
            $defaultModel,
            $apiKeyCipher,
            $apiKeyHint,
            time(),
        );

        $this->credentialRepository->update($credential);
    }

    /**
     * @return array{0: list<string>|null, 1: string|null}
     */
    private function razreshitModeli(IzmenitRekvizityOpenCode $command, OpenCodeCredential $credential): array
    {
        $legacyModel = trim((string) $command->model);

        if ($command->models === null) {
            if ($legacyModel === '') {
                // Набор не меняется; дефолт может быть изменён отдельно.
                return [null, $command->defaultModel];
            }

            // Легаси-клиент передал только `model` — заменяем набор на один элемент.
            return [[$legacyModel], $legacyModel];
        }

        if ($command->models === [] && $legacyModel !== '') {
            return [[$legacyModel], $command->defaultModel ?? $legacyModel];
        }

        return [$command->models, $command->defaultModel];
    }

    private function trebovatSvobodnoeImya(string $accountId, string $name, string $currentId): void
    {
        $existing = $this->credentialRepository->findByName($accountId, trim($name));

        if ($existing !== null && $existing->getId() !== $currentId) {
            throw new \DomainException('account.opencode_credential.name_double');
        }
    }
}
