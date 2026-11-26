<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Repo;

/**
 * Необратимый «намёк» на токен репозитория.
 *
 * Хранится рядом с шифротекстом, чтобы UI мог показать последние 4 символа
 * секрета, не расшифровывая его. Токены короче 8 символов маскируются
 * полностью: хвост короткого секрета раскрывал бы его существенную часть.
 *
 * Правило намеренно совпадает с публичным маппингом WS-1
 * (RepoListItem::maskToken()), чтобы контракт `tokenHint` не менялся.
 */
final class RepoTokenHint
{
    public const HINT_LENGTH = 4;

    public const MIN_TOKEN_LENGTH = 8;

    private function __construct()
    {
    }

    public static function fromPlainText(?string $token): string
    {
        if ($token === null || strlen($token) < self::MIN_TOKEN_LENGTH) {
            return '';
        }

        return substr($token, -self::HINT_LENGTH);
    }
}
