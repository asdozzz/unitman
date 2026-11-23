<?php

declare(strict_types=1);

namespace App\Runner\Business\Utils;

use App\Runner\Business\Model\RunnerState;

/**
 * Правило уровней угрозы по раннерам.
 *
 * Уровень берётся из снимка ресурсов, который считает сам раннер по порогам
 * 70% / 85% / 90%. Дополнительно несвежий health трактуется как `warning`:
 * для пользователей это такая же проблема, как жёлтый уровень ресурсов.
 */
final class UgrozyRannerov
{
    public const LEVEL_OK = 'ok';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_CRITICAL = 'critical';
    public const LEVEL_BLOCKER = 'blocker';

    /**
     * Эффективный уровень раннера: учитывает свежесть health-push.
     */
    public static function uroven(RunnerState $state): string
    {
        // Health-push давно не приходил — раннер недоступен или завис.
        if (!$state->isHealthFresh()) {
            return self::LEVEL_WARNING;
        }

        $resources = $state->getResources();

        if ($resources === null) {
            return self::LEVEL_OK;
        }

        return self::normalize($resources->level);
    }

    /**
     * Есть ли угроза (уровень выше `ok`).
     */
    public static function estUgroza(string $level): bool
    {
        return self::normalize($level) !== self::LEVEL_OK;
    }

    /**
     * Худший уровень из набора.
     *
     * @param string[] $levels
     */
    public static function hudshiy(array $levels): string
    {
        $worst = self::LEVEL_OK;

        foreach ($levels as $level) {
            if (self::rank($level) > self::rank($worst)) {
                $worst = self::normalize($level);
            }
        }

        return $worst;
    }

    /**
     * Порядок уровней: больше — хуже.
     */
    public static function rank(string $level): int
    {
        return match (self::normalize($level)) {
            self::LEVEL_BLOCKER => 3,
            self::LEVEL_CRITICAL => 2,
            self::LEVEL_WARNING => 1,
            default => 0,
        };
    }

    public static function normalize(string $level): string
    {
        $normalized = strtolower(trim($level));

        return match ($normalized) {
            self::LEVEL_WARNING, self::LEVEL_CRITICAL, self::LEVEL_BLOCKER => $normalized,
            default => self::LEVEL_OK,
        };
    }
}
