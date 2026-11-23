<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov;

/**
 * Проект, для которого нужно создать юнит в рамках групповой операции.
 *
 * Ветка берётся из снимка workspace и может отличаться у разных проектов.
 */
final class ProektDlyaSozdaniyaUnita
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $branch,
    ) {
    }
}
