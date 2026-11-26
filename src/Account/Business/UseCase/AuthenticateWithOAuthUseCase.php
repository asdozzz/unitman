<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Model\Account;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Model\OAuth\SocialIdentity;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanFindDouble;
use App\Account\Business\Port\CanHashPassword;
use App\Account\Business\Port\SocialIdentityRepository;
use App\Account\Business\Port\UmeetOpoveshatOZaproseAktivacii;
use App\Account\Business\Port\UuidGenerator;
use App\Account\Business\ReadModel\OAuthUserReadModel;
use App\Utils\Model\Locale\SupportedLocale;

/**
 * Аутентификация через Google/GitHub.
 *
 * Порядок:
 * 1. существующая social identity;
 * 2. существующий аккаунт по verified email — identity привязывается;
 * 3. новый аккаунт — создаётся confirmed, но pending-активация.
 */
final class AuthenticateWithOAuthUseCase
{
    public function __construct(
        private SocialIdentityRepository $socialIdentityRepository,
        private AccountRepository $accountRepository,
        private CanFindDouble $canFindDouble,
        private CanHashPassword $passwordHasher,
        private UuidGenerator $uuidGenerator,
        private UmeetOpoveshatOZaproseAktivacii $activationRequestNotifier,
        private string $defaultLocale,
    ) {
    }

    public function handle(string $provider, OAuthUserReadModel $oauthUser, string $locale = ''): string
    {
        $identity = $this->socialIdentityRepository->findByProviderAndUserId(
            $provider,
            $oauthUser->providerUserId,
        );

        if ($identity !== null) {
            $account = $this->accountRepository->getBy($identity->accountId);
            $this->assertCanLogin($account);

            return $account->getId();
        }

        $email = trim($oauthUser->email);
        $existingAccountId = $this->canFindDouble->findIdByEmail($email);

        if ($existingAccountId !== null) {
            return $this->linkExistingAccount($provider, $oauthUser, $existingAccountId, $email);
        }

        return $this->createAccount($provider, $oauthUser, $email, $locale);
    }

    private function linkExistingAccount(
        string $provider,
        OAuthUserReadModel $oauthUser,
        string $accountId,
        string $email,
    ): string {
        $account = $this->accountRepository->getBy($accountId);
        $this->assertCanLogin($account);

        $alreadyLinked = $this->socialIdentityRepository->findByAccountIdAndProvider($accountId, $provider);

        if ($alreadyLinked === null) {
            $this->socialIdentityRepository->save($this->makeIdentity(
                $accountId,
                $provider,
                $oauthUser->providerUserId,
                $email,
            ));
        }

        if (!$account->isEmailVerified()) {
            $account->confirmEmail();
            $this->accountRepository->save($account);

            $this->activationRequestNotifier->zaproshena(
                $account->getId(),
                $account->getEmail(),
                $account->getNickname() !== '' ? $account->getNickname() : null,
            );
        }

        return $accountId;
    }

    private function createAccount(
        string $provider,
        OAuthUserReadModel $oauthUser,
        string $email,
        string $locale,
    ): string {
        $accountId = $this->uuidGenerator->makeGuid();
        $passwordHash = $this->passwordHasher->hashPassword(bin2hex(random_bytes(32)));
        $resolvedLocale = SupportedLocale::normalize($locale !== '' ? $locale : $this->defaultLocale);

        $account = Account::registerOauthAccount(
            $accountId,
            $email,
            $passwordHash,
            $resolvedLocale,
            $oauthUser->nickname ?? '',
        );
        $this->accountRepository->save($account);

        $this->socialIdentityRepository->save($this->makeIdentity(
            $accountId,
            $provider,
            $oauthUser->providerUserId,
            $email,
        ));

        $this->activationRequestNotifier->zaproshena(
            $accountId,
            $email,
            $oauthUser->nickname,
        );

        return $accountId;
    }

    private function assertCanLogin(Account $account): void
    {
        if ($account->getRoleValue() === Role::ROLE_SYSTEM->value) {
            throw new \DomainException('oauth.account.system');
        }

        if ($account->isBlocked()) {
            throw new \DomainException('oauth.account.blocked');
        }
    }

    private function makeIdentity(
        string $accountId,
        string $provider,
        string $providerUserId,
        string $email,
    ): SocialIdentity {
        return new SocialIdentity(
            $this->uuidGenerator->makeGuid(),
            $accountId,
            $provider,
            $providerUserId,
            $email,
            time(),
        );
    }
}
