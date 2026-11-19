<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha;

use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;

/**
 * Декодирует сырое тело вебхука ровно один раз.
 *
 * Лимит проверяется до `json_decode`: большой push-payload размножается в
 * памяти worker'а RoadRunner (декодированный массив занимает в разы больше
 * исходной строки), поэтому слишком большое событие отклоняется ещё до разбора.
 * Ошибки отдаются стабильными кодами: вызывающий код (активность) логирует их,
 * возвращает наружу и не даёт заданию уйти в retry-цикл RoadRunner.
 */
final class SobitiePayloadDecoder
{
    /**
     * Верхний предел размера сырого события вебхука — 5 MiB.
     *
     * GitLab и GitHub укладывают push-события в единицы мегабайт; всё, что
     * больше, — либо аномалия, либо попытка исчерпать память воркера.
     */
    public const MAX_EVENT_PAYLOAD_BYTES = 5 * 1024 * 1024;

    /** Стабильный код: событие больше {@see self::MAX_EVENT_PAYLOAD_BYTES}. */
    public const ERROR_TOO_LARGE = 'payload_too_large';

    /** Стабильный код: тело не является JSON-массивом. */
    public const ERROR_MALFORMED = 'payload_malformed';

    /**
     * @throws SobitiePayloadException событие слишком велико или не разбирается
     */
    public function decode(string $eventPayload): SobitiePayload
    {
        if (strlen($eventPayload) > self::MAX_EVENT_PAYLOAD_BYTES) {
            throw new SobitiePayloadException(self::ERROR_TOO_LARGE);
        }

        try {
            $data = json_decode($eventPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new SobitiePayloadException(self::ERROR_MALFORMED);
        }

        if (!is_array($data)) {
            throw new SobitiePayloadException(self::ERROR_MALFORMED);
        }

        /** @var array<string, mixed> $data */
        return new SobitiePayload($eventPayload, $data);
    }
}
