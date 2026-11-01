<?php

namespace App\Runner\Business\Command\NachatPodgotovkuUnita;

/**
 * Том юнита, который нужно смонтировать в контейнер при подготовке.
 *
 * Если том приходит из медиатеки, то `FileId` содержит идентификатор
 * архива в медиатеке, `Url` — временную ссылку на скачивание zip-архива,
 * а `FileName` — исходное имя архива.
 */
final class Volume
{
    public function __construct(
        public readonly string $Dir,
        public readonly string $Name,
        public readonly ?string $Url = null,
        public readonly ?string $FileName = null,
        public readonly ?string $FileId = null
    )
    {
    }
}
