<?php

namespace App\Unitman\Business\ReadModel;

/**
 * Внутренняя read-модель репозитория (проекция `repo_list`).
 *
 * Хранит только шифротекст секрета и необратимый `tokenHint`. Открытый токен
 * в проекции не лежит. Публичный HTTP-контракт {@see RepoListItem} вообще
 * не отдаёт `tokenCipher`.
 */
class RepoList
{
    public string $id;
    public string $type;
    public string $name;
    public string $repoUrl;
    public string $tokenCipher = '';
    public string $tokenHint = '';
    public bool $confirmed;
}
