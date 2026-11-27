<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Файл-артефакт, созданный агентом в ходе OpenCode.
 *
 * Содержимого здесь нет: только метаданные, достаточные для списка вложений
 * и для решения, можно ли показать файл в preview.
 */
final class FajlHodaReadModel
{
    private const PROSMOTR_EXT = ['md', 'txt', 'json', 'yaml', 'yml', 'csv', 'log'];

    public function __construct(
        public readonly string $fileId,
        public readonly string $turnId,
        public readonly string $workspaceId,
        public readonly string $name,
        public readonly int $size,
        public readonly string $contentType,
        public readonly int $createdAt,
    ) {
    }

    public function podderzhivaetProsmotr(): bool
    {
        return in_array(
            strtolower((string) pathinfo($this->name, PATHINFO_EXTENSION)),
            self::PROSMOTR_EXT,
            true,
        );
    }

    /**
     * @return array{fileId: string, name: string, size: int, contentType: string}
     */
    public function toVlozhenie(): array
    {
        return [
            'fileId' => $this->fileId,
            'name' => $this->name,
            'size' => $this->size,
            'contentType' => $this->contentType,
        ];
    }

    /**
     * @return array{
     *     fileId: string,
     *     name: string,
     *     size: int,
     *     contentType: string,
     *     createdAt: int,
     *     workspaceId: string,
     *     turnId: string
     * }
     */
    public function toArray(): array
    {
        return [
            'fileId' => $this->fileId,
            'name' => $this->name,
            'size' => $this->size,
            'contentType' => $this->contentType,
            'createdAt' => $this->createdAt,
            'workspaceId' => $this->workspaceId,
            'turnId' => $this->turnId,
        ];
    }
}
