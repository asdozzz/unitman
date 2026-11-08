<?php

declare(strict_types=1);

namespace App\Unitman\Business\Utils;

/**
 * Работа со связями «переменная конфига → юнит, выбранный через хелпер».
 *
 * Утилита не зависит от инфраструктуры: принимает массивы конфига и связей,
 * чтобы её могли использовать и проекция связей, и read-модель списка юнитов.
 */
final class SvyaziPeremenihUtil
{
    /**
     * Переменные конфига, у которых настроен хелпер ссылки.
     *
     * @param array<array-key, mixed> $configUnita конфиг юнита в виде массива
     * @return array<string, array{projectId: string, service: string, port: int, suffix: ?string}>
     */
    static function poluchitPomoschnikiPoPeremennim(array $configUnita): array
    {
        $variables = $configUnita['variables'] ?? [];
        if (!is_array($variables)) {
            return [];
        }

        $result = [];
        foreach ($variables as $variable) {
            if (!is_array($variable)) {
                continue;
            }

            $variableId = $variable['id'] ?? null;
            $helper = $variable['helper'] ?? null;

            if (!is_string($variableId) || $variableId === '') {
                continue;
            }
            if (!is_array($helper) || empty($helper['projectId'])) {
                continue;
            }

            $result[$variableId] = [
                'projectId' => (string)$helper['projectId'],
                'service' => (string)($helper['service'] ?? ''),
                'port' => (int)($helper['port'] ?? 0),
                'suffix' => isset($helper['suffix']) ? (string)$helper['suffix'] : null,
            ];
        }

        return $result;
    }

    /**
     * Оставляет только те связи, для переменных которых хелпер ещё настроен,
     * и обогащает их данными хелпера (`projectId`, `service`, `port`, `suffix`).
     *
     * @param array<int, array<string, mixed>> $svyazi связи из события
     *        (`variableId`, `targetUnitId`, опционально `projectId`)
     * @param array<array-key, mixed> $configUnita конфиг юнита в виде массива
     * @return array<int, array{variableId: string, targetUnitId: string, projectId: string, service: string, port: int, suffix: ?string}>
     */
    static function sobratSvyaziPoKonfigu(array $svyazi, array $configUnita): array
    {
        $pomoschniki = self::poluchitPomoschnikiPoPeremennim($configUnita);

        $result = [];
        foreach ($svyazi as $svyaz) {
            $variableId = $svyaz['variableId'] ?? null;
            $targetUnitId = $svyaz['targetUnitId'] ?? null;

            if (!is_string($variableId) || !is_string($targetUnitId)) {
                continue;
            }
            if (!isset($pomoschniki[$variableId])) {
                continue;
            }

            $pomoschnik = $pomoschniki[$variableId];

            $result[] = [
                'variableId' => $variableId,
                'targetUnitId' => $targetUnitId,
                'projectId' => $pomoschnik['projectId'],
                'service' => $pomoschnik['service'],
                'port' => $pomoschnik['port'],
                'suffix' => $pomoschnik['suffix'],
            ];
        }

        return $result;
    }

    /**
     * Подготавливает связи события к записи в read-модель списка юнитов.
     *
     * Если конфиг недоступен, связи сохраняются как есть (без реконсиляции).
     *
     * @param array<int, array<string, mixed>> $svyazi
     * @param array<array-key, mixed>|null $configUnita
     * @return array<int, array<string, mixed>>
     */
    static function sobratSvyaziDlyaReadModel(array $svyazi, ?array $configUnita): array
    {
        if ($svyazi === []) {
            return [];
        }

        if ($configUnita === null || $configUnita === []) {
            return array_values(array_map(static function (array $svyaz): array {
                return [
                    'variableId' => (string)($svyaz['variableId'] ?? ''),
                    'targetUnitId' => (string)($svyaz['targetUnitId'] ?? ''),
                    'projectId' => (string)($svyaz['projectId'] ?? ''),
                    'service' => (string)($svyaz['service'] ?? ''),
                    'port' => (int)($svyaz['port'] ?? 0),
                    'suffix' => isset($svyaz['suffix']) ? (string)$svyaz['suffix'] : null,
                ];
            }, $svyazi));
        }

        return self::sobratSvyaziPoKonfigu($svyazi, $configUnita);
    }
}
