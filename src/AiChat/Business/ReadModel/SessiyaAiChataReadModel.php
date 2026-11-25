<?php

declare(strict_types=1);

namespace App\AiChat\Business\ReadModel;

use App\AiChat\Business\Model\SessiyaAiChata;

/**
 * Карточка сессии AI-чата для списка и экрана чата.
 *
 * `preview` — обрезанный текст первого сообщения сессии, `messageCount` —
 * количество сообщений. `credentialDeleted` говорит, что привязанные
 * реквизиты больше не существуют: сессия доступна только для чтения.
 */
final class SessiyaAiChataReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $provider,
        public readonly string $model,
        public readonly bool $archived,
        public readonly ?string $archiveReason,
        public readonly bool $credentialDeleted,
        public readonly string $preview,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly int $expiresAt,
        public readonly int $messageCount,
    ) {
    }

    /**
     * Собрать карточку из доменной сессии.
     *
     * `preview`, `messageCount` и `credentialDeleted` вычисляет вызывающий
     * слой, которому доступны сообщения и существующие реквизиты.
     */
    public static function izDomena(
        SessiyaAiChata $sessiya,
        string $preview = '',
        int $messageCount = 0,
        bool $credentialDeleted = false,
    ): self {
        return new self(
            $sessiya->id(),
            $sessiya->title(),
            $sessiya->provider(),
            $sessiya->model(),
            $sessiya->arhivirovana(),
            $sessiya->archiveReason(),
            $credentialDeleted,
            $preview,
            $sessiya->createdAt(),
            $sessiya->updatedAt(),
            $sessiya->expiresAt(),
            $messageCount,
        );
    }

    /**
     * Сформировать превью из текста первого сообщения.
     *
     * Текст обрезается по краям и до `SessiyaAiChata::PREVIEW_MAX_LENGTH`
     * символов (UTF-8). Пустой/null-текст даёт пустую строку.
     */
    public static function sdelatPreview(?string $content): string
    {
        if ($content === null) {
            return '';
        }

        $normalized = trim($content);

        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, SessiyaAiChata::PREVIEW_MAX_LENGTH);
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     provider: string,
     *     model: string,
     *     archived: bool,
     *     archiveReason: ?string,
     *     credentialDeleted: bool,
     *     preview: string,
     *     createdAt: int,
     *     updatedAt: int,
     *     expiresAt: int,
     *     messageCount: int
     * }
     */
    public function vMassiv(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'provider' => $this->provider,
            'model' => $this->model,
            'archived' => $this->archived,
            'archiveReason' => $this->archiveReason,
            'credentialDeleted' => $this->credentialDeleted,
            'preview' => $this->preview,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'expiresAt' => $this->expiresAt,
            'messageCount' => $this->messageCount,
        ];
    }
}
