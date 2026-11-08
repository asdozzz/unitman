<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Port;

/**
 * Проверка «файл медиатеки связан с внешней сущностью» — инверсия зависимости.
 *
 * Mediateka не знает про каталог типов томов и другие модули, поэтому
 * реализации порта регистрируются в тех модулях, где живут связи.
 * Удаление связанного файла блокируется.
 */
interface FileLinkChecker
{
    /**
     * Имя тега для регистрации реализаций в DI других модулей.
     */
    public const TAG = 'mediateka.file_link_checker';

    public function esliFaylPrivyazan(string $fileId): bool;
}
