<?php

declare(strict_types=1);

namespace App\Account\Business\Utils;

use App\Account\Business\Model\OpenCode\ProvayderOpenCode;

/**
 * Подготовка входных данных реквизитов OpenCode к сохранению.
 *
 * Правила (общие для создания и изменения):
 *  - известный провайдер и пустой URL → подставляется baseUrl каталога;
 *  - `custom` с пустым URL → ошибка `empty_url`;
 *  - пустой набор моделей и заполненный legacy `model` → `[model]`, дефолт = `model`.
 */
final class PodgotovitelRekvizitovOpenCode
{
    /**
     * @param list<string> $models
     *
     * @return array{0: list<string>, 1: string} набор моделей и дефолтная модель
     */
    public static function razreshitModeli(array $models, string $legacyModel, string $defaultModel): array
    {
        $legacyModel = trim($legacyModel);

        if ($models === [] && $legacyModel !== '') {
            $models = [$legacyModel];

            if (trim($defaultModel) === '') {
                $defaultModel = $legacyModel;
            }
        }

        return [$models, $defaultModel];
    }

    public static function urlDlyaProvaydera(ProvayderOpenCode $provider, string $url): string
    {
        $url = trim($url);

        if ($url !== '') {
            return $url;
        }

        $baseUrl = $provider->baseUrl();

        if ($baseUrl === '') {
            throw new \DomainException('account.opencode_credential.empty_url');
        }

        return $baseUrl;
    }
}
