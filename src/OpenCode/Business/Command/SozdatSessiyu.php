<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\OpenCode\Business\Model\ParametriSozdaniyaUnita;
use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Создание сессии диалога на выбранных реквизитах OpenCode.
 *
 * Реквизиты выбираются заранее и фиксируются в сессии: пользователь может
 * держать несколько ключей и раскладывать диалоги по разным моделям.
 *
 * Сессию можно сразу привязать к юнитам двумя взаимоисключающими способами:
 * `unitIds` — к уже существующим юнитам пользователя (первый становится рабочим
 * каталогом, остальные — дополнительными корнями workspace), `unit` — с созданием
 * нового юнита от имени владельца по текущей логике процессов Unitman.
 */
readonly class SozdatSessiyu implements JsonBodySerializableInterface
{
    /**
     * @param list<string> $unitIds
     */
    public function __construct(
        public string $credentialId = '',
        public ?string $title = null,
        public array $unitIds = [],
        public ?ParametriSozdaniyaUnita $unit = null,
        public ?string $model = null,
    ) {
    }
}
