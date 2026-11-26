<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ObmenyatOAuthKod;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanIssueAccessToken;
use App\Account\Business\Port\OAuthExchangeCodeRepository;
use App\Account\Business\ReadModel\OAuthTokenReadModel;

final class ObmenyatOAuthKodUseCase
{
    public function __construct(
        private OAuthExchangeCodeRepository $exchangeCodeRepository,
        private AccountRepository $accountRepository,
        private CanIssueAccessToken $accessTokenIssuer,
        private VipustitRefreshTokenUseCase $refreshTokenUseCase,
    ) {
    }

    public function handle(ObmenyatOAuthKod $command): OAuthTokenReadModel
    {
        $accountId = $this->exchangeCodeRepository->consume(
            hash('sha256', trim($command->code)),
            time(),
        );

        if ($accountId === null) {
            throw new \DomainException('oauth.code.invalid');
        }

        $account = $this->accountRepository->getBy($accountId);
        $nickname = $account->getNickname();

        return new OAuthTokenReadModel(
            $this->accessTokenIssuer->issue($accountId),
            $this->refreshTokenUseCase->handle($accountId),
            [
                'id' => $account->getId(),
                'email' => $account->getEmail(),
                'roles' => [$account->getRoleValue()],
                'locale' => $account->getLocale(),
                'nickname' => $nickname !== '' ? $nickname : null,
                'activationPending' => $account->isPendingActivation(),
                'emailVerified' => $account->isEmailVerified(),
            ],
        );
    }
}
