<?php

namespace App\Unitman\Business\Model\Unit\ConfigUnita\KonfigServisa;

final class CacheServisa
{
    /**
     * @param string[] $files
     * @param string[] $paths
     * */
    public function __construct(
        public readonly array $files,
        public readonly array $paths,
    )
    {
    }

    static function fromArray(array $cacheData): self
    {
        $files = $cacheData['files'] ?? [];
        $paths = $cacheData['paths'] ?? [];
        return new self($files, $paths);
    }

    function toArray(): array
    {
        return [
            'files' => $this->files,
            'paths' => $this->paths,
        ];
    }
}
