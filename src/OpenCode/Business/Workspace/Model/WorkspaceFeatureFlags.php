<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Model;

/**
 * Флаги cutover workspace-чата на самостоятельный агрегат.
 *
 * Валидация комбинации выполняется при создании сервиса (DI/старт):
 * - запись в новый агрегат без чтения из него невозможна: клиент прочитал бы
 *   устаревшее состояние из легаси;
 * - чтение нового агрегата без записи в него допустимо только при включённом
 *   зеркале легаси -> новый агрегат, иначе новый агрегат отстаёт.
 *
 * Легаси остаётся источником истины, пока флаги выключены (значения по
 * умолчанию).
 */
final class WorkspaceFeatureFlags
{
    public function __construct(
        private readonly bool $read = false,
        private readonly bool $write = false,
        private readonly bool $mirror = false,
    ) {
        if ($this->write && !$this->read) {
            throw new \InvalidArgumentException('opencode.workspace.flags.write_requires_read');
        }

        if ($this->read && !$this->write && !$this->mirror) {
            throw new \InvalidArgumentException('opencode.workspace.flags.read_requires_mirror');
        }
    }

    public function chitatIzNovogoAgregata(): bool
    {
        return $this->read;
    }

    public function pisatVNoviyAgregat(): bool
    {
        return $this->write;
    }

    public function zerkaloVklyucheno(): bool
    {
        return $this->mirror;
    }
}
