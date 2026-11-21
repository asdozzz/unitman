<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\LichniyToken;

/**
 * Публичное представление личного токена: без шифротекста и открытого секрета.
 */
final class LichniyTokenPublicReadModel
{
    /**
     * @param string[] $proektySPushom
     */
    public function __construct(
        public readonly string $id,
        public readonly string $repoId,
        public readonly string $nazvanie,
        public readonly string $tokenHint,
        public readonly string $obyomPrav,
        public readonly array $proektySPushom,
        public readonly int $proverenoV,
        public readonly bool $bezPravNaRedaktirovanie = false,
    ) {
    }

    public static function izList(LichniyTokenList $list, bool $bezPravNaRedaktirovanie = false): self
    {
        return new self(
            $list->id,
            $list->repoId,
            $list->nazvanie,
            $list->tokenHint,
            $list->obyomPrav,
            $list->proektySPushom,
            $list->proverenoV,
            $bezPravNaRedaktirovanie,
        );
    }
}
