<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\SecurityService;

/**
 * Статус активации текущего пользователя.
 *
 * Доступен и неактивированному аккаунту: по нему фронтенд понимает, что пора
 * пускать пользователя в приложение.
 */
final class PoluchitMoiStatusQuery
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository,
    ) {
    }

    /**
     * @return array{activationPending: bool, emailVerified: bool, roles: list<string>}
     */
    public function handle(): array
    {
        $account = $this->accountRepository->getBy($this->securityService->getCurrentUserId());

        return [
            'activationPending' => $account->isPendingActivation(),
            'emailVerified' => $account->isEmailVerified(),
            'roles' => [$account->getRoleValue()],
        ];
    }
}
