<?php

namespace App\Utils\Service;

final class LockService
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

        return file_get_contents($this->getFilePath());
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
        $filename = 'lock.json';
        return $filename;
    }

    /**
     * @return string
     */
    private function getFilePath(): string
    {
        return $this->projectDir . '/' . $this->getLockFileName();
    }
}
