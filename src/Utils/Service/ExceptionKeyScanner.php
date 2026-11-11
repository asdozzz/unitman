<?php

declare(strict_types=1);

namespace App\Utils\Service;

/**
 * Collects dictionary keys used in the source code.
 *
 * Two different sets are collected:
 *  - "thrown" keys - string literals passed to an exception constructor, i.e.
 *    messages that will be shown to the API client and therefore must exist in
 *    the canonical dictionary;
 *  - "referenced" keys - every quoted dot-key found anywhere in the module
 *    sources, used to detect dictionary entries that nothing points at anymore.
 *
 * The scanner never depends on the translator, so it can be used by both the
 * audit command and the dictionary consistency test.
 */
final class ExceptionKeyScanner
{
    /**
     * Matches a key literal passed to an exception constructor, e.g.
     * `new \DomainException("unit.not_found")`, including namespaced classes.
     */
    private const THROW_PATTERN = '/new\s+[\\\\\w]*(?:Exception|Dump)\s*\(\s*\'([^\']+)\'/';

    /**
     * Any quoted dot-key, optionally followed by a free-form detail suffix the
     * translator appends after translation, e.g. "unit.process.type_unknown: build".
     */
    private const LITERAL_KEY_PATTERN = '/\'([a-z][a-zA-Z0-9_]*(?:\.[a-zA-Z0-9_]+)+)(?::[^\']*)?\'/';

    public function __construct(private string $projectDir)
    {
    }

    /**
     * Keys passed to an exception constructor, sorted and unique.
     *
     * @return list<string>
     */
    public function scanThrownKeys(string $relativeDir = 'src'): array
    {
        $keys = [];

        foreach ($this->phpFiles($relativeDir) as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match_all(self::THROW_PATTERN, $contents, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $message) {
                $key = $this->extractKey($message);

                if ($key !== null) {
                    $keys[$key] = true;
                }
            }
        }

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }

    /**
     * Every quoted dot-key referenced in the module sources, sorted and unique.
     *
     * @return list<string>
     */
    public function scanReferencedKeys(string $relativeDir = 'src'): array
    {
        $keys = [];

        foreach ($this->phpFiles($relativeDir) as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match_all(self::LITERAL_KEY_PATTERN, $contents, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $message) {
                $keys[$message] = true;
            }
        }

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }

    /**
     * Tells whether a string is shaped like a dictionary key.
     */
    public function looksLikeKey(string $message): bool
    {
        return preg_match('/^[a-z][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)+$/', trim($message)) === 1;
    }

    /**
     * Returns the dictionary key of an exception message, dropping a
     * free-form detail suffix the translator would append after translation.
     */
    public function extractKey(string $message): ?string
    {
        // The source literal usually ends right after the separator
        // ("...: "), so trimming first would hide it - split before trimming.
        $message = explode(':', $message, 2)[0];
        $message = trim($message);

        return $this->looksLikeKey($message) ? $message : null;
    }

    /**
     * Flattens a nested dictionary file into dot-keys.
     *
     * @param array<array-key, mixed> $dictionary
     *
     * @return array<string, string>
     */
    public static function flatten(array $dictionary, string $prefix = ''): array
    {
        $flat = [];

        foreach ($dictionary as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flat += self::flatten($value, $fullKey);
                continue;
            }

            $flat[$fullKey] = (string) $value;
        }

        return $flat;
    }

    /**
     * Loads a PHP dictionary file and returns its flat key/value pairs.
     *
     * @return array<string, string>
     */
    public function loadDictionary(string $relativeFile): array
    {
        $path = $this->projectDir . '/' . ltrim($relativeFile, '/');

        if (!is_file($path)) {
            return [];
        }

        $dictionary = require $path;

        if (!is_array($dictionary)) {
            return [];
        }

        return self::flatten($dictionary);
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $relativeDir): array
    {
        $root = $this->projectDir . '/' . trim($relativeDir, '/');

        if (!is_dir($root)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }
}
