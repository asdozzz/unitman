<?php

declare(strict_types=1);

namespace App\Utils\Service;

use App\Utils\Model\Locale\SupportedLocale;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translates exception messages.
 *
 * Messages that look like dictionary keys (e.g. "unit.not_found") are resolved
 * through the translator in the requested locale; anything else (human-written
 * messages, SQL errors, third-party messages) is returned unchanged, so the API
 * contract never loses information.
 *
 * The locale is always passed explicitly to the translator - the global locale
 * is never mutated, because RoadRunner workers are long-lived and such a change
 * would leak into the next request.
 */
final class ExceptionTranslator
{
    /**
     * Keys are dot-separated lowercase segments. A few historical keys keep a
     * camelCase segment (e.g. "unit.projectId_is_empty"), so upper-case letters
     * are allowed after the first character of a segment.
     */
    private const KEY_PATTERN = '/^[a-z][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)+$/';

    private const DETAIL_SEPARATOR = ': ';

    public function __construct(
        private TranslatorInterface $translator,
        private string $defaultLocale,
        private ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * Translates a message (dictionary key) into the given locale.
     *
     * Unknown keys and free-form messages are returned untouched; a key with an
     * appended detail ("mediateka.file.not_found: 42") keeps the detail.
     */
    public function translate(string $message, ?string $locale = null): string
    {
        $message = trim($message);

        if ($message === '') {
            return $message;
        }

        if ($this->isKey($message)) {
            return $this->doTranslate($message, $this->resolveLocale($locale));
        }

        $separatorPosition = strpos($message, self::DETAIL_SEPARATOR);

        if ($separatorPosition !== false) {
            $key = substr($message, 0, $separatorPosition);
            $detail = substr($message, $separatorPosition + strlen(self::DETAIL_SEPARATOR));

            if ($this->isKey($key)) {
                return $this->doTranslate($key, $this->resolveLocale($locale)) . self::DETAIL_SEPARATOR . $detail;
            }
        }

        return $message;
    }

    /**
     * Translates an exception message, returning the canonical generic error
     * when the message is empty.
     */
    public function translateThrowable(\Throwable $exception, ?string $locale = null): string
    {
        $locale = $this->resolveLocale($locale);
        $message = $this->translate($exception->getMessage(), $locale);

        if ($message !== '') {
            return $message;
        }

        return $this->doTranslate('common.internal_error', $locale);
    }

    /**
     * Tells whether the given string is a dictionary key.
     */
    public function isKey(string $message): bool
    {
        return preg_match(self::KEY_PATTERN, $message) === 1;
    }

    /**
     * The canonical locale used as the final fallback.
     */
    public function canonLocale(): string
    {
        return SupportedLocale::normalize($this->defaultLocale);
    }

    /**
     * Uses the current request locale when none is given explicitly.
     */
    private function resolveLocale(?string $locale): string
    {
        if ($locale !== null && $locale !== '') {
            return SupportedLocale::normalize($locale);
        }

        return SupportedLocale::normalize($this->requestStack?->getCurrentRequest()?->getLocale());
    }

    private function doTranslate(string $key, string $locale): string
    {
        $translated = $this->translator->trans($key, [], 'messages', $locale);

        if ($translated !== $key) {
            return $translated;
        }

        $canon = $this->canonLocale();

        if ($locale === $canon) {
            return $translated;
        }

        return $this->translator->trans($key, [], 'messages', $canon);
    }
}