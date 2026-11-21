<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Model\LichniyToken\ObyomPrav;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenList;
use App\Unitman\Business\UseCase\Unit\Redaktor\OpredelitelRezhimaRedaktora;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Матрица прав 15.7-а: редактор + токен + включённый режим + не защищённая ветка.
 */
final class OpredelitelRezhimaRedaktoraTest extends TestCase
{
    private const PROJECT_ID = 'project-1';
    private const AUTHOR_ID = 'author-1';

    #[Test]
    public function avtor_s_pokrivayushim_tokenom_poluchaet_edit(): void
    {
        $result = $this->opredelit(
            'author-1',
            ['author-1'],
            [],
            true,
            false,
            [$this->token('author-1', self::PROJECT_ID)],
        );

        self::assertSame('edit', $result['redaktorMode']);
        self::assertTrue($result['moguRedaktirovat']);
        self::assertNull($result['prichinaZapreta']);
        self::assertFalse($result['vetkaZashishena']);
    }

    #[Test]
    public function avtor_bez_tokena_ne_mozhet_redit(): void
    {
        $result = $this->opredelit('author-1', ['author-1'], [], true, false, []);

        self::assertSame('view', $result['redaktorMode']);
        self::assertFalse($result['moguRedaktirovat']);
        self::assertSame('net_tokena', $result['prichinaZapreta']);
    }

    #[Test]
    public function token_bez_pokritiya_proekta_ne_daet_edit(): void
    {
        $result = $this->opredelit(
            'author-1',
            ['author-1'],
            [],
            true,
            false,
            [$this->token('author-1', 'other-project')],
        );

        self::assertSame('view', $result['redaktorMode']);
        self::assertFalse($result['moguRedaktirovat']);
        self::assertSame('net_tokena', $result['prichinaZapreta']);
    }

    #[Test]
    public function prikreplennii_redaktor_poluchaet_edit(): void
    {
        $result = $this->opredelit(
            'editor-1',
            ['author-1', 'editor-1'],
            [],
            true,
            false,
            [$this->token('editor-1', self::PROJECT_ID)],
        );

        self::assertSame('edit', $result['redaktorMode']);
        self::assertTrue($result['moguRedaktirovat']);
    }

    #[Test]
    public function postoronnii_ne_poluchaet_edit_dazhe_s_tokenom(): void
    {
        $result = $this->opredelit(
            'stranger-1',
            ['author-1'],
            [],
            true,
            false,
            [$this->token('stranger-1', self::PROJECT_ID)],
        );

        self::assertSame('view', $result['redaktorMode']);
        self::assertFalse($result['moguRedaktirovat']);
        self::assertNull($result['prichinaZapreta']);
    }

    #[Test]
    public function zashishennaya_vetka_daet_locked_dazhe_avtoru(): void
    {
        $result = $this->opredelit(
            'author-1',
            ['author-1'],
            ['release/*'],
            true,
            false,
            [$this->token('author-1', self::PROJECT_ID)],
        );

        self::assertSame('locked', $result['redaktorMode']);
        self::assertFalse($result['moguRedaktirovat']);
        self::assertSame('protected_branch', $result['prichinaZapreta']);
        self::assertTrue($result['vetkaZashishena']);
    }

    #[Test]
    public function vyklyuchennii_rezhim_daet_view(): void
    {
        $result = $this->opredelit(
            'author-1',
            ['author-1'],
            [],
            false,
            false,
            [$this->token('author-1', self::PROJECT_ID)],
        );

        self::assertSame('view', $result['redaktorMode']);
        self::assertFalse($result['moguRedaktirovat']);
        self::assertNull($result['prichinaZapreta']);
    }

    #[Test]
    public function sistemnii_unit_vsegda_view(): void
    {
        $result = $this->opredelit(
            'system',
            ['system'],
            [],
            true,
            true,
            [$this->token('system', self::PROJECT_ID)],
        );

        self::assertSame('view', $result['redaktorMode']);
        self::assertFalse($result['moguRedaktirovat']);
    }

    /**
     * @param string[] $redaktory
     * @param string[] $zashishennieVetki
     * @param LichniyTokenList[] $tokens
     * @return array{vetkaZashishena: bool, redaktorMode: string, moguRedaktirovat: bool, prichinaZapreta: string|null}
     */
    private function opredelit(
        string $userId,
        array $redaktory,
        array $zashishennieVetki,
        bool $vklucheno,
        bool $system,
        array $tokens,
    ): array {
        $project = $this->project($zashishennieVetki);
        $repository = $this->createMock(LichniyTokenListRepository::class);
        $projectRepository = $this->createMock(ProjectRepository::class);

        $opredelitel = new OpredelitelRezhimaRedaktora($repository, $projectRepository);

        $branch = $zashishennieVetki === [] ? 'feature/1' : 'release/1.2';

        return $opredelitel->opredelit(
            $project,
            $branch,
            self::AUTHOR_ID,
            $redaktory,
            $vklucheno,
            $system,
            $userId,
            $tokens,
        );
    }

    private function token(string $accountId, string $projectId): LichniyTokenList
    {
        return new LichniyTokenList(
            'token-' . $accountId,
            $accountId,
            'repo-1',
            'token',
            'cipher',
            '****1234',
            ObyomPrav::Spisok->value,
            [$projectId],
            time(),
            false,
        );
    }

    /**
     * @param string[] $zashishennieVetki
     */
    private function project(array $zashishennieVetki): Project
    {
        return Project::addProject(
            self::PROJECT_ID,
            new AddProject(
                'repo-1',
                'group/project',
                'Project',
                'main',
                'https://proxy.local',
                300,
                $zashishennieVetki,
            ),
            self::AUTHOR_ID,
        );
    }
}
