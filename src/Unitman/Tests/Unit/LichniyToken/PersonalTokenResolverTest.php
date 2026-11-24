<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\LichniyToken;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\LichniyToken;
use App\Unitman\Business\Model\LichniyToken\ObyomPrav;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenList;
use App\Unitman\Infra\Adapter\PersonalTokenOpenSslShifr;
use App\Unitman\Infra\Adapter\PersonalTokenResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PersonalTokenResolverTest extends TestCase
{
    private const KEY_PLAIN = 'test-only-personal-token-key-32b';

    private const ACCOUNT_ID = 'account-1';

    private const REPO_ID = 'repo-1';

    private const PROJECT_ID = 'project-1';

    #[Test]
    public function vozvrashchaet_rasshifrovanniy_token_pokryvayushchiy_proekt(): void
    {
        $shifr = $this->shifr();
        $token = 'glpat-secret-1';
        $resolver = $this->resolver(
            [$this->tokenList('token-1', $token, ObyomPrav::Vse, [], 100, $shifr)],
            $shifr,
        );

        self::assertSame($token, $resolver->poluchitToken('unit-1', self::ACCOUNT_ID));
    }

    #[Test]
    public function vizbiraet_samiy_svezhiy_token(): void
    {
        $shifr = $this->shifr();
        $resolver = $this->resolver(
            [
                $this->tokenList('token-old', 'old-token', ObyomPrav::Vse, [], 100, $shifr),
                $this->tokenList('token-new', 'new-token', ObyomPrav::Vse, [], 200, $shifr),
            ],
            $shifr,
        );

        self::assertSame('new-token', $resolver->poluchitToken('unit-1', self::ACCOUNT_ID));
    }

    #[Test]
    public function bez_tokena_dlya_repo_oshibka_net_lichnogo_tokena(): void
    {
        $shifr = $this->shifr();
        $resolver = $this->resolver([], $shifr);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.net_lichnogo_tokena');

        $resolver->poluchitToken('unit-1', self::ACCOUNT_ID);
    }

    #[Test]
    public function token_bez_prav_na_proekt_otklonyaetsya(): void
    {
        $shifr = $this->shifr();
        $resolver = $this->resolver(
            [$this->tokenList('token-1', 'secret', ObyomPrav::Spisok, ['other-project'], 100, $shifr)],
            $shifr,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.token_bez_prav_na_proekt');

        $resolver->poluchitToken('unit-1', self::ACCOUNT_ID);
    }

    #[Test]
    public function poluchit_token_dlya_proekta_vozvrashchaet_token(): void
    {
        $shifr = $this->shifr();
        $token = 'glpat-project-secret';
        $resolver = $this->resolver(
            [$this->tokenList('token-1', $token, ObyomPrav::Vse, [], 100, $shifr)],
            $shifr,
        );

        self::assertSame($token, $resolver->poluchitTokenDlyProekta(self::PROJECT_ID, self::ACCOUNT_ID));
    }

    #[Test]
    public function poluchit_token_dlya_proekta_bez_tokena_oshibka(): void
    {
        $shifr = $this->shifr();
        $resolver = $this->resolver([], $shifr);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.net_lichnogo_tokena');

        $resolver->poluchitTokenDlyProekta(self::PROJECT_ID, self::ACCOUNT_ID);
    }

    #[Test]
    public function poluchit_token_dlya_proekta_bez_prav_otklonyaetsya(): void
    {
        $shifr = $this->shifr();
        $resolver = $this->resolver(
            [$this->tokenList('token-1', 'secret', ObyomPrav::Spisok, ['other-project'], 100, $shifr)],
            $shifr,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.token_bez_prav_na_proekt');

        $resolver->poluchitTokenDlyProekta(self::PROJECT_ID, self::ACCOUNT_ID);
    }

    private function resolver(array $tokens, PersonalTokenOpenSslShifr $shifr): PersonalTokenResolver
    {
        $projectId = self::PROJECT_ID;
        $repoId = self::REPO_ID;
        $accountId = self::ACCOUNT_ID;

        $unit = Unit::sozdatUnit(
            'unit-1',
            $accountId,
            'prozes-1',
            new SozdatUnit($projectId, 'unit-1', 'feature/123', [], 300),
        );

        $project = Project::addProject(
            $projectId,
            new AddProject($repoId, 'group/project-1', 'project-one', 'master', 300),
            $accountId,
        );

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('getById')->willReturn($unit);

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturn($project);

        $listRepository = $this->createMock(LichniyTokenListRepository::class);
        $listRepository->method('findByAccountId')->willReturn($tokens);

        return new PersonalTokenResolver($unitRepository, $projectRepository, $listRepository, $shifr);
    }

    /**
     * @param string[] $proekty
     */
    private function tokenList(
        string $id,
        string $plain,
        ObyomPrav $obyomPrav,
        array $proekty,
        int $proverenoV,
        PersonalTokenOpenSslShifr $shifr,
    ): LichniyTokenList {
        return new LichniyTokenList(
            $id,
            self::ACCOUNT_ID,
            self::REPO_ID,
            'token',
            $shifr->zashifrovat($plain, LichniyToken::makeAad(self::ACCOUNT_ID, $id)),
            '****',
            $obyomPrav->value,
            $proekty,
            $proverenoV,
            false,
        );
    }

    private function shifr(): PersonalTokenOpenSslShifr
    {
        return new PersonalTokenOpenSslShifr(base64_encode(self::KEY_PLAIN));
    }
}
