<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Utils\DopuskaemyeFaylyWorkspace;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Правила файлов workspace: allowlist из конфига, жёсткий denylist,
 * нормализация имени и проверка содержимого.
 */
final class DopuskaemyeFaylyWorkspaceTest extends TestCase
{
    private const MAX_SIZE = 1024 * 1024;

    private const ALLOWED = 'png,jpg,txt,md,json,sh,php,unknown';

    private function validator(string $allowed = self::ALLOWED, int $maxFiles = 20): DopuskaemyeFaylyWorkspace
    {
        return new DopuskaemyeFaylyWorkspace($allowed, self::MAX_SIZE, $maxFiles);
    }

    #[Test]
    public function razreshennye_rasshireniya_isklyuchayut_zapreshchennye_i_neizvestnye(): void
    {
        $extensions = $this->validator()->razreshennyeRasshireniya();

        $this->assertContains('png', $extensions);
        $this->assertContains('jpg', $extensions);
        $this->assertContains('txt', $extensions);
        $this->assertContains('md', $extensions);
        $this->assertContains('json', $extensions);
        $this->assertNotContains('sh', $extensions, 'скрипты запрещены даже при наличии в env');
        $this->assertNotContains('php', $extensions, 'php запрещён даже при наличии в env');
        $this->assertNotContains('unknown', $extensions);
    }

    #[Test]
    public function imya_normalizuetsya_i_privoditsya_k_bezopasnomu_vidu(): void
    {
        $validator = $this->validator();

        $this->assertSame('my-file.txt', $validator->normalizovatImya('My File.TXT'));
        $this->assertSame('notes.md', $validator->normalizovatImya('../../notes.md'));
        $this->assertSame('file.txt', $validator->normalizovatImya('...txt'));
    }

    #[Test]
    public function pustoе_imya_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.name_is_empty');

        $this->validator()->normalizovatImya('   ');
    }

    #[Test]
    public function imya_bez_rasshireniya_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.name_without_extension');

        $this->validator()->normalizovatImya('README');
    }

    #[Test]
    public function zapreshchennyi_tip_otklonyaetsya_dazhe_esli_est_v_allowlist(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.file_type_forbidden');

        $this->validator()->normalizovatImya('deploy.sh');
    }

    #[Test]
    public function tip_vne_allowlist_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.file_type_forbidden');

        $this->validator()->normalizovatImya('archive.zip');
    }

    #[Test]
    public function rezervirovannoe_imya_otklonyaetsya(): void
    {
        $validator = $this->validator('txt,code-workspace');

        try {
            $validator->normalizovatImya('workspace.code-workspace');
            $this->fail('ожидалось исключение для резервного имени');
        } catch (DomainException $error) {
            $this->assertSame('mediateka.workspace.file_type_forbidden', $error->getMessage());
        }
    }

    #[Test]
    public function slishkom_dlinnoe_imya_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.name_is_too_long');

        $this->validator()->normalizovatImya(str_repeat('a', DopuskaemyeFaylyWorkspace::NAME_MAX_LENGTH) . '.txt');
    }

    #[Test]
    public function razmer_fayla_proveryaetsya(): void
    {
        $path = $this->tempFile('ok');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.file_is_too_large');

        $this->validator()->proveritFayl($path, 'notes.txt', self::MAX_SIZE + 1);
    }

    #[Test]
    public function pustoi_fayl_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.file_is_empty');

        $this->validator()->proveritFayl($this->tempFile(''), 'notes.txt', 0);
    }

    #[Test]
    public function tekst_s_nul_baitom_schitaetsya_binarnym(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.file_is_binary');

        $this->validator()->proveritFayl($this->tempFile("hello\0world"), 'notes.txt', 11);
    }

    #[Test]
    public function nevalidnyi_utf8_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.file_is_not_utf8');

        $this->validator()->proveritFayl($this->tempFile("\xC3\x28"), 'notes.txt', 2);
    }

    #[Test]
    public function validnyi_tekst_prinimaetsya(): void
    {
        $this->validator()->proveritFayl($this->tempFile('Привет, мир!'), 'notes.txt', 23);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function izobrazhenie_s_nevernoi_signaturoi_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.file_signature_mismatch');

        $this->validator()->proveritFayl($this->tempFile('not a png'), 'image.png', 9);
    }

    #[Test]
    public function validnoe_izobrazhenie_prinimaetsya(): void
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            true,
        );
        \assert(is_string($png));

        $this->validator()->proveritFayl($this->tempFile($png), 'image.png', strlen($png));

        $this->addToAssertionCount(1);
    }

    private function tempFile(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'unitman_ws_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->tempFiles = [];
    }
}
