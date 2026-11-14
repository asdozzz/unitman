<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Создание сессии диалога на выбранных реквизитах OpenCode.
 *
 * Реквизиты выбираются заранее и фиксируются в сессии: пользователь может
 * держать несколько ключей и раскладывать диалоги по разным моделям.
 */
readonly class SozdatSessiyu implements JsonBodySerializableInterface
{
    public function __construct(
        public string $credentialId = '',
        public ?string $title = null,
    ) {
    }
}