<?php

declare(strict_types=1);

namespace App\Utils\Model\Locale;

/**
 * Languages supported by the exception dictionaries.
 *
 * The canonical locale (en) is the language dictionaries are authored in and
 * the fallback used when a translation is missing for the requested locale.
 */
enum SupportedLocale: string
{
    case EN = 'en';
    case RU = 'ru';

    /**
     * Canonical locale value, safe to use in constant expressions
     * (e.g. constructor default values).
     */
    public const CANON_VALUE = 'en';

    public const CANON = self::EN;

    /**
     * Returns the canonical locale value ("en").
     */
    public static function canon(): string
    {
        return self::CANON_VALUE;
    }

    /**
     * Tells whether the given raw locale code is supported.
     */
    public static function isSupported(string $locale): bool
    {
        return self::tryFrom($locale) !== null;
    }

    /**
     * Normalises an arbitrary locale string (e.g. "ru_RU", "en-US", "RU")
     * to a supported locale value, falling back to the canonical locale.
     */
    public static function normalize(?string $locale): string
    {
        if ($locale === null || $locale === '') {
            return self::canon();
        }

        $normalized = strtolower(trim($locale));
        $normalized = str_replace('_', '-', $normalized);
        $primary = explode('-', $normalized)[0];

        return self::tryFrom($primary)?->value ?? self::canon();
    }

    /**
     * Returns all supported locale values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $locale): string => $locale->value,
            self::cases(),
        );
    }
}
