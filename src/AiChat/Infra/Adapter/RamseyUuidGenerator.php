<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Adapter;

use App\AiChat\Business\Port\UuidGenerator;
use App\Utils\Service\AbstractRamseyGuidGenerator;

/**
 * UUID-генератор AI-чата на базе Ramsey (UUID v7 из общего адаптера).
 */
final class RamseyUuidGenerator extends AbstractRamseyGuidGenerator implements UuidGenerator
{
    public function generit(): string
    {
        return $this->makeGuid();
    }
}
