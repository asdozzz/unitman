<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\ReadModel\FajlHodaReadModel;
use App\OpenCode\Business\ReadModel\WorkspaceFileReadModel;

/**
 * Подмена файлов workspace для тестов без Mediateka и S3.
 *
 * Файл не оканчивается на `Test.php`, поэтому PHPUnit не собирает его как тест.
 */
final class ImitaciyaUpravleniyaFaylamiWorkspace implements UmeetUpravlyatFaylamiWorkspace
{
    /**
     * @var array<string, list<WorkspaceFileReadModel>>
     */
    private array $faylyPoWorkspace = [];

    /**
     * @var array<string, WorkspaceFileReadModel>
     */
    private array $chernoviki = [];

    /**
     * @var list<string>
     */
    private array $udalennie = [];

    /**
     * @var list<string>
     */
    private array $primenennye = [];

    /**
     * Файлы ходов: turnId => fileId => read model.
     *
     * @var array<string, array<string, FajlHodaReadModel>>
     */
    private array $fajlyHodov = [];

    /**
     * Содержимое файлов ходов: fileId => text.
     *
     * @var array<string, string>
     */
    private array $soderzhimoeFajlov = [];

    private bool $dostupny = true;

    /** Глобальный счётчик: fileId уникален между ходами. */
    private int $nextFileId = 1;

    /**
     * @param list<WorkspaceFileReadModel> $files
     */
    public function zadatFayly(string $workspaceId, array $files): void
    {
        $this->faylyPoWorkspace[$workspaceId] = $files;
    }

    public function zadatChernovik(WorkspaceFileReadModel $file): void
    {
        $this->chernoviki[$file->fileId] = $file;
    }

    public function zadatDostupnost(bool $dostupny): void
    {
        $this->dostupny = $dostupny;
    }

    /**
     * @return list<string>
     */
    public function udalennye(): array
    {
        return $this->udalennie;
    }

    /**
     * @return list<string>
     */
    public function primenennye(): array
    {
        return $this->primenennye;
    }

    public function poluchitFayly(string $workspaceId): array
    {
        return $this->faylyPoWorkspace[$workspaceId] ?? [];
    }

    public function poluchitFaylyPoImId(array $workspaceIds): array
    {
        $result = [];

        foreach ($workspaceIds as $workspaceId) {
            $result[$workspaceId] = $this->faylyPoWorkspace[$workspaceId] ?? [];
        }

        return $result;
    }

    public function dostupniDlyaPrivyazki(array $fileIds, string $accountId): bool
    {
        return $this->dostupny;
    }

    public function prikrepit(string $workspaceId, array $fileIds): array
    {
        $result = [];

        foreach ($fileIds as $fileId) {
            $file = $this->chernoviki[$fileId] ?? null;

            if ($file === null) {
                continue;
            }

            $result[] = $file;
            $this->faylyPoWorkspace[$workspaceId][] = $file;
        }

        return $result;
    }

    public function otmetitPrimenenimi(array $fileIds): void
    {
        foreach ($fileIds as $fileId) {
            $this->primenennye[] = $fileId;
        }
    }

    public function poluchitSsylkiDlyaFaylov(array $fileIds): array
    {
        $result = [];

        foreach ($fileIds as $fileId) {
            $file = $this->chernoviki[$fileId] ?? null;

            if ($file === null) {
                continue;
            }

            $result[$fileId] = ['name' => $file->name, 'url' => 'https://example.test/' . $fileId];
        }

        return $result;
    }

    public function udalitFayl(string $fileId): void
    {
        $this->udalennie[] = $fileId;
        unset($this->chernoviki[$fileId]);
    }

    public function zaregistrirovatFajlHoda(
        string $workspaceId,
        string $accountId,
        string $turnId,
        string $name,
        string $contentType,
        string $content,
        int $now,
    ): FajlHodaReadModel {
        // Идемпотентность пары (turnId, name): retry Temporal не создаёт дубль.
        foreach ($this->fajlyHodov[$turnId] ?? [] as $existing) {
            if ($existing->name === $name) {
                return $existing;
            }
        }

        $fileId = 'hod-file-' . $this->nextFileId++;

        $file = new FajlHodaReadModel(
            $fileId,
            $turnId,
            $workspaceId,
            $name,
            strlen($content),
            $contentType,
            $now,
        );

        $this->fajlyHodov[$turnId][$fileId] = $file;
        $this->soderzhimoeFajlov[$fileId] = $content;

        return $file;
    }

    public function poluchitFajlHoda(string $turnId, string $fileId): ?FajlHodaReadModel
    {
        return $this->fajlyHodov[$turnId][$fileId] ?? null;
    }

    public function poluchitStatistikuFajlovHoda(string $turnId): array
    {
        $count = 0;
        $totalSize = 0;

        foreach ($this->fajlyHodov[$turnId] ?? [] as $file) {
            $count++;
            $totalSize += $file->size;
        }

        return ['count' => $count, 'totalSize' => $totalSize];
    }

    public function poluchitSoderzhimoeFajla(string $fileId, int $maxBytes): string
    {
        $content = $this->soderzhimoeFajlov[$fileId] ?? null;

        if ($content === null) {
            throw new \DomainException('mediateka.file.not_found');
        }

        if (strlen($content) > $maxBytes) {
            throw new \DomainException('mediateka.file.too_large');
        }

        if (str_contains($content, "\0") || !mb_check_encoding($content, 'UTF-8')) {
            throw new \DomainException('mediateka.file.content_is_invalid');
        }

        return $content;
    }

    public function poluchitSsylkuDlyaFajlaHoda(string $turnId, string $fileId): ?string
    {
        if (!isset($this->fajlyHodov[$turnId][$fileId])) {
            return null;
        }

        return 'https://example.test/hod/' . $fileId;
    }

    public function udalitFajlyWorkspace(string $workspaceId): void
    {
        foreach ($this->fajlyHodov as $turnId => $files) {
            foreach ($files as $fileId => $file) {
                if ($file->workspaceId === $workspaceId) {
                    unset($this->fajlyHodov[$turnId][$fileId], $this->soderzhimoeFajlov[$fileId]);
                }
            }
        }
    }
}
