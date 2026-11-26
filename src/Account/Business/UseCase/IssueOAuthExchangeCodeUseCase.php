<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Port\OAuthExchangeCodeRepository;

final class IssueOAuthExchangeCodeUseCase
{
    public function __construct(
        private OAuthExchangeCodeRepository $exchangeCodeRepository,
        private int $oauthExchangeCodeTtl,
    ) {
    }

    public function handle(string $accountId): string
    {
        $code = bin2hex(random_bytes(32));
        $now = time();

        $this->exchangeCodeRepository->save(
            hash('sha256', $code),
            $accountId,
            $now + $this->oauthExchangeCodeTtl,
            $now,
        );

        return $code;
    }
}
