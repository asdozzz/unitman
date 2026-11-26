<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

use App\Account\Business\ReadModel\OAuthUserReadModel;

interface OAuthProvayder
{
    public function imya(): string;

    public function nastroen(): bool;

    public function ssylkaAvtorizacii(string $state, string $codeVerifier): string;

    public function poluchitPolzovatelya(string $code, string $codeVerifier): OAuthUserReadModel;
}
