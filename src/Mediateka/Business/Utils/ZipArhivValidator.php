<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Utils;

/**
 * Проверка, что переданный поток является zip-архивом.
 *
 * Signature-based проверка используется намеренно: расширение zip недоступно в контейнере,
 * а полная валидация центрального каталога не нужна — файл всё равно уходит в хранилище as-is.
 */
final class ZipArhivValidator
{
    /**
     * Локальный заголовок файла.
     */
    private const SIGNATURE_LOCAL_FILE_HEADER = "PK\x03\x04";

    /**
     * Конец центрального каталога — пустой архив.
     */
    private const SIGNATURE_END_OF_CENTRAL_DIRECTORY = "PK\x05\x06";

    /**
     * @param mixed $stream поток, полученный из fopen(); тип проверяется в рантайме
     */
    public static function etoZipArhiv($stream): bool
    {
        if (!is_resource($stream)) {
            return false;
        }

        $position = ftell($stream);
        if (!$position && !is_int($position)) {
            return false;
        }

        rewind($stream);
        $header = fread($stream, 4);
        fseek($stream, $position);

        if ($header === false || strlen($header) < 4) {
            return false;
        }

        return $header === self::SIGNATURE_LOCAL_FILE_HEADER
            || $header === self::SIGNATURE_END_OF_CENTRAL_DIRECTORY;
    }
}
