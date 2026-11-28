<?php

declare(strict_types=1);

namespace App\OpenCode\Business\AgentMode\Model;

/**
 * Персональные настройки мода: реквизиты, модель, видимость и порядок.
 *
 * Строка может отсутствовать — это значит «настройки не заданы». Для
 * персонального мода сам мод всё равно доступен владельцу.
 */
final class NastroykaRezhimaAgenta
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $modeId,
        public readonly ?string $credentialId,
        public readonly ?string $model,
        public readonly bool $enabled,
        public readonly int $sortOrder,
        public readonly int $updatedAt,
    ) {
    }

    public static function poUmolchaniyu(string $accountId, string $modeId, int $now): self
    {
        return new self($accountId, $modeId, null, null, true, 0, $now);
    }

    public function sRekvizitami(?string $credentialId, ?string $model, int $now): self
    {
        return new self(
            $this->accountId,
            $this->modeId,
            $this->normalize($credentialId),
            $this->normalize($model),
            $this->enabled,
            $this->sortOrder,
            $now,
        );
    }

    public function sVidimostyu(bool $enabled, int $sortOrder, int $now): self
    {
        return new self(
            $this->accountId,
            $this->modeId,
            $this->credentialId,
            $this->model,
            $enabled,
            max(0, $sortOrder),
            $now,
        );
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
