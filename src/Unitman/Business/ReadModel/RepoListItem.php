<?php

namespace App\Unitman\Business\ReadModel;

/**
 * Публичное представление репозитория для HTTP-ответов и AI-инструментов.
 *
 * В отличие от внутренней модели {@see RepoList}, которая хранится в
 * проекции и содержит секрет доступа, этот read model НЕ содержит `token`.
 * Вместо него отдаётся необратимый `tokenHint` — последние 4 символа.
 *
 * Такой отдельный класс гарантирует, что учётные данные не утекут ни по
 * одному пути сериализации: контроллеры и генераторы AI-инструментов видят
 * только этот объект.
 */
final class RepoListItem
{
    /**
     * Длина хвоста токена, попадающего в публичный ответ.
     */
    private const HINT_LENGTH = 4;

    /**
     * Токены короче этого значения маскируются полностью (пустая строка),
     * чтобы хвост не раскрывал существенную часть секрета.
     */
    private const MIN_TOKEN_LENGTH = 8;

    public string $id;
    public string $type;
    public string $name;
    public string $repoUrl;
    public string $tokenHint;
    public bool $confirmed;

    public static function fromRepoList(RepoList $repo): self
    {
        $item = new self();
        $item->id = $repo->id;
        $item->type = $repo->type;
        $item->name = $repo->name;
        $item->repoUrl = $repo->repoUrl;
        // Проекция уже хранит необратимый hint; открытый токен не нужен и
        // не должен попадать в публичный ответ ни при каких условиях.
        $item->tokenHint = $repo->tokenHint;
        $item->confirmed = $repo->confirmed;

        return $item;
    }

    /**
     * Возвращает необратимый намёк на токен: последние 4 символа.
     *
     * Для отсутствующего или короткого (< 8 символов) токена возвращается
     * пустая строка: хвост короткого секрета раскрывал бы его существенную
     * часть, а полное значение не возвращается никогда.
     */
    public static function maskToken(?string $token): string
    {
        if ($token === null || strlen($token) < self::MIN_TOKEN_LENGTH) {
            return '';
        }

        return substr($token, -self::HINT_LENGTH);
    }
}
