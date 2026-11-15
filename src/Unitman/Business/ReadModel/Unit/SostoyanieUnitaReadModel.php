<?php

namespace App\Unitman\Business\ReadModel\Unit;

/**
 * Минимальное описание состояния юнита для внешних модулей (например,
 * сессий OpenCode). Наружу отдаётся только код состояния, а не внутренние
 * классы состояний юнита.
 */
final class SostoyanieUnitaReadModel
{
    public function __construct(
        public readonly bool $exists,
        public readonly ?string $code = null,
        public readonly ?string $projectId = null,
        public readonly ?string $name = null,
        public readonly ?string $branch = null,
        public readonly ?string $authorId = null,
    )
    {
    }

    /**
     * Юнита нет (удалён или не найден): все остальные поля не имеют смысла.
     */
    public static function otsutstvuet(): self
    {
        return new self(false);
    }

    public static function izUnita(
        string $code,
        string $projectId,
        string $name,
        string $branch,
        ?string $authorId,
    ): self
    {
        return new self(true, $code, $projectId, $name, $branch, $authorId);
    }
}
