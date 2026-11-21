<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port;

/**
 * Симметричное шифрование секретов Unitman (личные токены хранилищ).
 *
 * Шифротекст обязан быть привязан к контексту (AAD): его нельзя «переставить»
 * из одной записи в другую. Реализация живёт в Infra, чтобы Business-слой
 * не зависел от конкретного расширения PHP.
 */
interface UmeetShifrovatSekrety
{
    public function zashifrovat(string $secret, string $aad): string;

    /**
     * @throws \DomainException если шифротекст повреждён, подменён или AAD не совпадает
     */
    public function rasshifrovat(string $cipher, string $aad): string;
}
