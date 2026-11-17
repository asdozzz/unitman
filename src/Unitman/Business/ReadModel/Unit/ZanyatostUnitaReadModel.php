<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Unit;

use App\Unitman\Business\Model\Unit\TipZanyatosti;

/**
 * Причина, по которой над юнитом нельзя выполнять действия.
 *
 * Две составляющие объединены в одну read-модель, потому что вызывающему
 * модулю нужен единый ответ «занят / свободен и почему»:
 *
 * - `exists` — есть активный внешний захват (например, идёт ход OpenCode);
 * - `zanyatProzesami` — у юнита есть незавершённые собственные процессы
 *   (сборка, обновление, действие и т.п.), вычисляется по агрегату.
 *
 * `tip` осмыслен только при `exists === true`.
 */
final class ZanyatostUnitaReadModel
{
    public function __construct(
        public readonly bool $exists,
        public readonly bool $zanyatProzesami = false,
        public readonly ?TipZanyatosti $tip = null,
        public readonly ?\DateTimeImmutable $istekaetV = null,
    )
    {
    }

    public static function svoboden(): self
    {
        return new self(false);
    }

    public function zanyat(): bool
    {
        return $this->exists || $this->zanyatProzesami;
    }

    /**
     * Строковое значение причины для интерфейса: `opencode_hod`, `prozes`
     * или `null`, если юнит свободен.
     */
    public function prichina(): ?string
    {
        if ($this->exists) {
            return $this->tip?->value;
        }

        return $this->zanyatProzesami ? TipZanyatosti::PROZES->value : null;
    }
}
