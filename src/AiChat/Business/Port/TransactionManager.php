<?php

declare(strict_types=1);

namespace App\AiChat\Business\Port;

/**
 * Менеджер транзакций бизнес-слоя.
 *
 * Отделяет use case от конкретного соединения с БД: бизнес-код описывает
 * границы атомарной работы, а инфраструктура решает, как её выполнить.
 * Сейчас используется ходами (turn), но порт заведён на этапе сессий,
 * чтобы не менять сигнатуры при добавлении ходов.
 */
interface TransactionManager
{
    /**
     * Выполнить работу в одной транзакции.
     *
     * @template T
     *
     * @param callable(): T $rabota
     *
     * @return T результат работы
     */
    public function vTransakcii(callable $rabota): mixed;
}
