<?php

declare(strict_types=1);

namespace App\Skill\Business\ReadModel;

/**
 * Эффективный набор навыков с манифестом.
 *
 * Формат `manifestHash` (контракт для Go-раннера):
 *
 *   line        = "<containerName>:<version>:<hash>"
 *   manifest    = sort_ascending(lines) joined with "\n"
 *   manifestHash = lowercase hex sha256(manifest)
 *
 * Строки сортируются лексикографически (побайтово, как `sort()` в PHP /
 * `sort.Strings()` в Go). Завершающего перевода строки нет. Для пустого
 * набора хеш равен sha256("").
 */
final class RezultatEffektivnyhNavykov
{
    /** @var list<EffektivnyyNavyk> */
    private readonly array $navyki;

    private readonly int $totalBytes;

    private readonly string $manifestHash;

    /**
     * @param list<EffektivnyyNavyk> $navyki
     */
    public function __construct(array $navyki, private readonly bool $truncated)
    {
        $this->navyki = $navyki;

        $bytes = 0;
        $lines = [];

        foreach ($navyki as $navyk) {
            $bytes += strlen($navyk->getContent());
            $lines[] = $navyk->getContainerName() . ':' . $navyk->getVersion() . ':' . $navyk->getHash();
        }

        sort($lines, SORT_STRING);

        $this->totalBytes = $bytes;
        $this->manifestHash = hash('sha256', implode("\n", $lines));
    }

    /**
     * @return list<EffektivnyyNavyk>
     */
    public function getNavyki(): array
    {
        return $this->navyki;
    }

    public function getManifestHash(): string
    {
        return $this->manifestHash;
    }

    public function getTotalBytes(): int
    {
        return $this->totalBytes;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    /**
     * @return array{
     *     skills: list<array<string, mixed>>,
     *     manifestHash: string,
     *     totalBytes: int,
     *     truncated: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'skills' => array_map(
                static fn (EffektivnyyNavyk $navyk): array => $navyk->toArray(),
                $this->navyki,
            ),
            'manifestHash' => $this->manifestHash,
            'totalBytes' => $this->totalBytes,
            'truncated' => $this->truncated,
        ];
    }
}
