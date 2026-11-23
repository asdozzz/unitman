<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase\OpenCode;

use App\Account\Business\Command\SpisokModeleiProvaydera;
use App\Account\Business\Model\OpenCode\OshibkaZaprosaModelei;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Model\OpenCode\ProvayderOpenCode;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UmeetShifrovatSekrety;
use App\Account\Business\Port\ZaprositModeliProvaydera;
use App\Account\Business\ReadModel\SpisokModeleiReadModel;

/**
 * Загрузка списка моделей провайдера для формы реквизитов.
 *
 * Владелец всегда берётся из JWT. Ключ берётся либо из сохранённых реквизитов
 * (с проверкой владения), либо одноразово из запроса — во втором случае он
 * нигде не сохраняется. Если провайдер не поддерживает live-список или запрос
 * завершился ошибкой, форма переходит в ручной режим: возвращается пустой
 * список и (при ошибке) ключ перевода.
 */
final class PoluchitModeliProvayderaUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private OpenCodeCredentialRepository $credentialRepository,
        private UmeetShifrovatSekrety $umeetShifrovatSekrety,
        private ZaprositModeliProvaydera $zaprositModeli,
    ) {
    }

    public function handle(SpisokModeleiProvaydera $command): SpisokModeleiReadModel
    {
        $accountId = $this->securityService->getCurrentUserId();
        $providerCode = trim($command->provider);

        try {
            $provider = ProvayderOpenCode::izKoda($providerCode);
        } catch (\DomainException $exception) {
            return new SpisokModeleiReadModel($providerCode, [], 'manual', $exception->getMessage());
        }

        $apiKey = $this->poluchitKluch($command, $accountId);

        if (!$provider->podderzhivaetSpisokModelei()) {
            return new SpisokModeleiReadModel(
                $provider->value,
                [],
                'manual',
                null,
            );
        }

        try {
            $models = $this->zaprositModeli->zaprosit($provider, $apiKey);

            return new SpisokModeleiReadModel($provider->value, $models, 'live', null);
        } catch (\DomainException $exception) {
            $status = $exception instanceof OshibkaZaprosaModelei ? $exception->status : null;

            return new SpisokModeleiReadModel(
                $provider->value,
                [],
                'manual',
                $exception->getMessage(),
                $status,
            );
        }
    }

    private function poluchitKluch(SpisokModeleiProvaydera $command, string $accountId): string
    {
        if ($command->credentialId !== null && trim($command->credentialId) !== '') {
            $credential = $this->credentialRepository->getById(trim($command->credentialId));
            $credential->trebovatVladelca($accountId);

            return $this->umeetShifrovatSekrety->rasshifrovat(
                $credential->getApiKeyCipher(),
                OpenCodeCredential::makeAad($credential->getAccountId(), $credential->getId()),
            );
        }

        return $command->apiKey ?? '';
    }
}
