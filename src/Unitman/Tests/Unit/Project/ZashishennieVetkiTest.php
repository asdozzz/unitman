<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Project;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\UstanovitZashishennieVetkiProekta;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\Event\ProjectWasAdded;
use App\Unitman\Business\Model\Project\Event\ZashishennieVetkiProektaUstanovleni;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ZashishennieVetkiTest extends TestCase
{
    private const PROJECT_ID = 'project-1';

    #[Test]
    public function bez_zashishennih_vetok_nichego_ne_zashisheno(): void
    {
        $project = $this->sozdatProekt([]);

        self::assertFalse($project->soderzhitZashishennuyuVetku('main'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('release/1.0'));
    }

    #[Test]
    public function tochnoe_sovpadenie_sopostavlyaetsya_po_vsemu_imeni(): void
    {
        $project = $this->sozdatProekt(['main']);

        self::assertTrue($project->soderzhitZashishennuyuVetku('main'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('mainline'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('Main'));
        self::assertFalse($project->soderzhitZashishennuyuVetku(' feature/main'));
    }

    #[Test]
    public function zvezdochka_sopostavlyaet_lyubuyu_posledovatelnost_vklyuchaya_slash(): void
    {
        $project = $this->sozdatProekt(['release/*']);

        self::assertTrue($project->soderzhitZashishennuyuVetku('release/1.2'));
        self::assertTrue($project->soderzhitZashishennuyuVetku('release/'));
        self::assertTrue($project->soderzhitZashishennuyuVetku('release/feature/x'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('releases/1.2'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('xrelease/1.2'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('release'));
    }

    #[Test]
    public function odna_zvezdochka_zashishaet_vsyo(): void
    {
        $project = $this->sozdatProekt(['*']);

        self::assertTrue($project->soderzhitZashishennuyuVetku('main'));
        self::assertTrue($project->soderzhitZashishennuyuVetku('feature/123/fix'));
        self::assertTrue($project->soderzhitZashishennuyuVetku(''));
    }

    #[Test]
    public function zvezdochka_v_seredine_i_v_konce(): void
    {
        $project = $this->sozdatProekt(['feature/*/fix', 'hotfix-*']);

        self::assertTrue($project->soderzhitZashishennuyuVetku('feature/1/fix'));
        self::assertTrue($project->soderzhitZashishennuyuVetku('feature/a/b/fix'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('feature/1/fix2'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('feature/1/other'));

        self::assertTrue($project->soderzhitZashishennuyuVetku('hotfix-urgent'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('hotfix'));
    }

    #[Test]
    public function neskolko_patternov_podderzhivayutsya(): void
    {
        $project = $this->sozdatProekt(['main', 'release/*', '*/fix']);

        self::assertTrue($project->soderzhitZashishennuyuVetku('main'));
        self::assertTrue($project->soderzhitZashishennuyuVetku('release/2.0'));
        self::assertTrue($project->soderzhitZashishennuyuVetku('bug/fix'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('develop'));
    }

    #[Test]
    public function regex_simvoly_v_patternah_sopostavlyayutsya_bukvalno(): void
    {
        $project = $this->sozdatProekt(['release/v1.2', 'fix+(test)', 'a[b]']);

        self::assertTrue($project->soderzhitZashishennuyuVetku('release/v1.2'));
        self::assertTrue($project->soderzhitZashishennuyuVetku('fix+(test)'));
        self::assertTrue($project->soderzhitZashishennuyuVetku('a[b]'));

        self::assertFalse($project->soderzhitZashishennuyuVetku('release/v1x2'));
        self::assertFalse($project->soderzhitZashishennuyuVetku('fix(test)'));
    }

    #[Test]
    public function ustanovka_vetok_menyaet_sostav_zashishennih(): void
    {
        $project = $this->sozdatProekt(['main']);
        $project->releaseEvents();

        $project->ustanovitZashishennieVetki(new UstanovitZashishennieVetkiProekta(self::PROJECT_ID, ['release/*']));

        self::assertSame(['release/*'], $project->poluchitZashishennieVetki());
        self::assertFalse($project->soderzhitZashishennuyuVetku('main'));
        self::assertTrue($project->soderzhitZashishennuyuVetku('release/1.0'));

        $events = $project->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ZashishennieVetkiProektaUstanovleni::class, $events[0]);
        self::assertSame(['release/*'], $events[0]->zashishennieVetki);
    }

    #[Test]
    public function sozdanie_proekta_zapisyvaet_zashishennie_vetki_v_event(): void
    {
        $project = $this->sozdatProekt(['main', 'release/*']);

        $events = $project->releaseEvents();
        $sobytie = null;

        foreach ($events as $event) {
            if ($event instanceof ProjectWasAdded) {
                $sobytie = $event;
            }
        }

        self::assertNotNull($sobytie);
        self::assertSame(['main', 'release/*'], $sobytie->zashishennieVetki);
        self::assertSame(['main', 'release/*'], $project->poluchitZashishennieVetki());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nevvalidnieVetki(): iterable
    {
        yield 'пустая строка' => [''];
    }

    #[Test]
    #[DataProvider('nevvalidnieVetki')]
    public function pustaya_stroka_otklonyaetsya(string $vetka): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('project.zashishennie_vetki.pusta_stroka');

        $this->sozdatProekt([$vetka]);
    }

    #[Test]
    public function slishkom_dlinnaya_vetka_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('project.zashishennie_vetki.slishkom_dlinnaya');

        $this->sozdatProekt([str_repeat('a', 257)]);
    }

    #[Test]
    public function dublikat_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('project.zashishennie_vetki.dublikat');

        $this->sozdatProekt(['main', 'main']);
    }

    #[Test]
    public function slishkom_mnogo_vetok_otklonyaetsya(): void
    {
        $vetki = array_map(static fn (int $i): string => 'branch-' . $i, range(1, 51));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('project.zashishennie_vetki.slishkom_mnogo');

        $this->sozdatProekt($vetki);
    }

    #[Test]
    public function dopustimo_maksimum_vetok(): void
    {
        $vetki = array_map(static fn (int $i): string => 'branch-' . $i, range(1, 50));

        $project = $this->sozdatProekt($vetki);

        self::assertCount(50, $project->poluchitZashishennieVetki());
    }

    /**
     * @param string[] $vetki
     */
    private function sozdatProekt(array $vetki): Project
    {
        return Project::addProject(
            self::PROJECT_ID,
            new AddProject('repo-1', 'group/project', 'Project', 'main',
                300,
                $vetki,
            ),
            'user-1',
        );
    }
}
