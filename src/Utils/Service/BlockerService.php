<?php

namespace App\Utils\Service;

final class BlockerService
{
    public function __construct(private string $projectDir)
    {
    }

    function lock(string $message = 'The application locked'): void
    {
        file_put_contents($this->getFilePath(), $message);
    }

    function isLock(): bool
    {
        return file_exists($this->getFilePath());
    }

    function getLockContent(): string
    {
        if (!$this->isLock()) {
            return "";
        }

        $file_get_contents = file_get_contents($this->getFilePath());

        if ($file_get_contents === false) {
            throw new \RuntimeException('Unable to read lock file');
        }

        return $file_get_contents;
    }

    function unlock(): void
    {
        if (file_exists($this->getFilePath())) {
            unlink($this->getFilePath());
        }
    }

    /**
     * @return string
     */
    private function getLockFileName(): string
    {
        return 'lock.json';
    }

    /**
     * @return string
     */
    private function getFilePath(): string
    {
        return $this->projectDir . '/' . $this->getLockFileName();
    }
}
