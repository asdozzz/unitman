<?php

declare(strict_types=1);

namespace App\Utils\Tests;

use App\Utils\Model\Locale\SupportedLocale;
use App\Utils\Service\ExceptionKeyScanner;
use PHPUnit\Framework\TestCase;

/**
 * Guards the exception dictionaries.
 *
 * The canonical (en) dictionary defines the key set: every supported locale
 * must mirror it exactly, and every key thrown from the code must be present.
 * A failure here means an API client would receive a raw dot-key instead of a
 * human-readable message.
 */
final class DictionariesConsistencyTest extends TestCase
{
    private const CANON_FILE = 'translations/messages.en.php';

    private ExceptionKeyScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new ExceptionKeyScanner(dirname(__DIR__, 3));
    }

    public function test_every_supported_locale_mirrors_the_canonical_key_set(): void
    {
        $canon = $this->scanner->loadDictionary(self::CANON_FILE);

        self::assertNotEmpty($canon, 'The canonical dictionary must not be empty.');

        foreach (SupportedLocale::values() as $locale) {
            $dictionary = $this->scanner->loadDictionary(sprintf('translations/messages.%s.php', $locale));

            self::assertSame(
                [],
                array_values(array_diff(array_keys($canon), array_keys($dictionary))),
                sprintf('[%s] is missing keys present in the canonical dictionary.', $locale),
            );
            self::assertSame(
                [],
                array_values(array_diff(array_keys($dictionary), array_keys($canon))),
                sprintf('[%s] contains keys absent from the canonical dictionary.', $locale),
            );
        }
    }

    public function test_no_translation_is_empty(): void
    {
        foreach (SupportedLocale::values() as $locale) {
            $dictionary = $this->scanner->loadDictionary(sprintf('translations/messages.%s.php', $locale));

            foreach ($dictionary as $key => $message) {
                self::assertNotSame('', trim($message), sprintf('[%s] "%s" is empty.', $locale, $key));
            }
        }
    }

    public function test_every_thrown_key_has_a_canonical_translation(): void
    {
        $canon = $this->scanner->loadDictionary(self::CANON_FILE);
        $thrown = $this->scanner->scanThrownKeys();

        self::assertNotEmpty($thrown, 'No exception keys were found in the sources - the scanner is broken.');

        self::assertSame(
            [],
            array_values(array_diff($thrown, array_keys($canon))),
            'Keys thrown from the code must exist in the canonical dictionary.',
        );
    }
}
