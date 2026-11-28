<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;

/**
 * Выбор модели хода OpenCode.
 *
 * Приоритет: модель из запроса -> модель по умолчанию (участника или сессии)
 * -> дефолтная модель реквизитов. Любая выбранная модель проверяется через порт
 * реквизитов: недопустимая модель отклоняется кодом
 * `account.opencode_credential.model_not_available`.
 */
final class OpredelitelModeliHodaOpenCode
{
    public function __construct(private UmeetProveryatRekvizityOpenCode $credentials)
    {
    }

    public function opredelit(
        string $accountId,
        string $credentialId,
        ?string $modelIzZaprosa,
        ?string $modelPoUmolchaniyu,
    ): string {
        $preferred = $this->normalize($modelIzZaprosa) ?? $this->normalize($modelPoUmolchaniyu);

        return $this->credentials->razreshitModel($accountId, $credentialId, $preferred);
    }

    private function normalize(?string $model): ?string
    {
        if ($model === null) {
            return null;
        }

        $model = trim($model);

        return $model === '' ? null : $model;
    }
}
