<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Repo;

use App\Unitman\Business\ReadModel\RepoList;
use App\Unitman\Business\ReadModel\RepoListItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Маскирование учётных данных репозитория в публичном read model (WS-1).
 */
final class RepoListItemTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function tokens(): iterable
    {
        yield 'typical token keeps last 4' => ['TESTTOKENEXAMPLEVALUE', 'ALUE'];
        yield 'exactly at minimum length' => ['12345678', '5678'];
        yield 'shorter than minimum is fully masked' => ['1234567', ''];
        yield 'very short is fully masked' => ['abc', ''];
        yield 'empty is fully masked' => ['', ''];
        yield 'null is fully masked' => [null, ''];
        yield 'long token keeps only 4 chars' => ['ghp_EXAMPLEaaaaaaaaaaaaaaaaaaaa', 'aaaa'];
    }

    #[Test]
    #[DataProvider('tokens')]
    public function mask_token_never_reveals_full_value(?string $token, string $expected): void
    {
        $hint = RepoListItem::maskToken($token);

        self::assertSame($expected, $hint);
        if ($token !== null && $token !== '') {
            self::assertNotSame($token, $hint);
        }
    }

    #[Test]
    public function from_repo_list_drops_token_and_keeps_non_secret_fields(): void
    {
        $repo = new RepoList();
        $repo->id = 'repo-id';
        $repo->type = 'gitlab';
        $repo->name = 'repo-name';
        $repo->repoUrl = 'https://example.invalid/repo';
        $repo->token = 'TESTTOKENEXAMPLEVALUE';
        $repo->confirmed = true;

        $item = RepoListItem::fromRepoList($repo);

        self::assertSame('repo-id', $item->id);
        self::assertSame('gitlab', $item->type);
        self::assertSame('repo-name', $item->name);
        self::assertSame('https://example.invalid/repo', $item->repoUrl);
        self::assertSame('ALUE', $item->tokenHint);
        self::assertTrue($item->confirmed);

        self::assertFalse(
            property_exists($item, 'token'),
            'Публичный read model не должен содержать свойство token.'
        );
    }
}
