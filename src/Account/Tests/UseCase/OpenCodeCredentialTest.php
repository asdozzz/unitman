<?php

declare(strict_types=1);

namespace App\Account\Tests\UseCase;

use App\Account\Api\AccountApi;
use App\Account\Business\Command\IzmenitRekvizityOpenCode;
use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Command\SozdatRekvizityOpenCode;
use App\Account\Business\Command\UdalitRekvizityOpenCode;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\UseCase\OpenCode\IzmenitRekvizityOpenCodeUseCase;
use App\Account\Business\UseCase\OpenCode\PoluchitSpisokRekvizitovOpenCodeQuery;
use App\Account\Business\UseCase\OpenCode\SozdatRekvizityOpenCodeUseCase;
use App\Account\Business\UseCase\OpenCode\UdalitRekvizityOpenCodeUseCase;
use App\Account\Business\UseCase\RegisterAccountUseCase;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * CRUD реквизитов OpenCode через UseCase-слой поверх настоящей БД.
 *
 * Проверяем не форму DTO, а контракт хранения: открытый ключ не попадает
 * в БД, маска отдаётся в список, чужие реквизиты недоступны, а расшифровка
 * возможна только для владельца.
 */
final class OpenCodeCredentialTest extends AbstractTestCaseWithTransactionWrapper
{
    private string $currentAccountId = '';

    private ?MockObject $securityService = null;

    /**
     * Подменяет текущего пользователя в SecurityService.
     *
     * Мок создаётся один раз, а id читается лениво: иначе пришлось бы
     * пересобирать контейнер между шагами сценария.
     */
    private function podmenitPolzovatelya(string $accountId): void
    {
        $this->currentAccountId = $accountId;

        if ($this->securityService !== null) {
            return;
        }

        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())
            ->method('getCurrentUserId')
            ->willReturnCallback(fn (): string => $this->currentAccountId);

        self::$container->set(SecurityService::class, $securityService);
        $this->securityService = $securityService;
    }

    private function zaregistrirovatAccount(): string
    {
        $this->podmenitPolzovatelya('');

        $sut = self::$container->get(RegisterAccountUseCase::class);
        \assert($sut instanceof RegisterAccountUseCase);

        $accountId = $sut->handle(
            new RegisterAccount(uniqid('opencode_', true) . '@asd.ru', 'asd', Role::ROLE_USER->value, 'ru')
        );

        $this->assertNotEmpty($accountId);

        $this->podmenitPolzovatelya($accountId);

        return $accountId;
    }

    private function sozdatRekvizity(
        string $name = 'local',
        string $url = 'https://opencode.local',
        string $model = 'claude-sonnet-4',
        string $apiKey = 'sk-test-1234',
    ): string {
        $sut = self::$container->get(SozdatRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof SozdatRekvizityOpenCodeUseCase);

        return $sut->handle(new SozdatRekvizityOpenCode($name, $url, $model, $apiKey));
    }

    /**
     * @return array<string, mixed>
     */
    private function syrayaZapis(string $credentialId): array
    {
        $row = self::$db?->fetchAssociative(
            'select * from opencode_credential where id = :id',
            ['id' => $credentialId]
        );

        $this->assertIsArray($row, 'Запись реквизитов не найдена в БД');

        return $row;
    }

    #[Test]
    public function klyuch_ne_lozhitsya_v_bd_otkrytym_tekstom(): void
    {
        $this->zaregistrirovatAccount();

        $credentialId = $this->sozdatRekvizity(apiKey: 'sk-super-secret-1234');

        $row = $this->syrayaZapis($credentialId);

        $this->assertNotSame('sk-super-secret-1234', $row['api_key_cipher']);
        $this->assertStringNotContainsString('sk-super-secret-1234', (string) $row['api_key_cipher']);
        $this->assertSame('****1234', $row['api_key_hint'], 'В БД должна лежать только маска');
        $this->assertSame(1, (int) $row['cipher_version']);
        $this->assertSame('https://opencode.local', $row['url']);
        $this->assertSame('claude-sonnet-4', $row['model']);
    }

    #[Test]
    public function spisok_soderzhit_masku_i_ne_raskryvaet_klyuch(): void
    {
        $this->zaregistrirovatAccount();

        $this->sozdatRekvizity('beta', 'https://b.local', 'model-b', 'sk-bbbb-9999');
        $this->sozdatRekvizity('alpha', 'https://a.local', 'model-a', 'sk-aaaa-1111');

        $sut = self::$container->get(PoluchitSpisokRekvizitovOpenCodeQuery::class);
        \assert($sut instanceof PoluchitSpisokRekvizitovOpenCodeQuery);

        $items = $sut->handle();

        $this->assertCount(2, $items);
        // Сортировка по имени: список в UI не должен «прыгать».
        $this->assertSame(['alpha', 'beta'], array_column($items, 'name'));
        $this->assertSame(['****1111', '****9999'], array_column($items, 'apiKeyHint'));
        $this->assertStringNotContainsString('sk-', implode('', array_column($items, 'apiKeyHint')));
    }

    #[Test]
    public function pustoi_klyuch_pri_sozdanii_zapreshchen(): void
    {
        $this->zaregistrirovatAccount();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.empty_api_key');

        // Иначе в БД появилась бы запись с пустым секретом, и раннер
        // упал бы уже на своей стороне, без внятной причины.
        $this->sozdatRekvizity('main', apiKey: '   ');
    }

    #[Test]
    public function povtornoe_imya_zapreshcheno(): void
    {
        $this->zaregistrirovatAccount();

        $this->sozdatRekvizity('main');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.name_double');

        $this->sozdatRekvizity('main');
    }

    #[Test]
    public function odinakovoe_imya_u_raznyh_accountov_razresheno(): void
    {
        $this->zaregistrirovatAccount();
        $this->sozdatRekvizity('main');

        $this->zaregistrirovatAccount();
        $credentialId = $this->sozdatRekvizity('main');

        $this->assertNotEmpty($credentialId);
    }

    #[Test]
    public function izmenenie_bez_klyucha_sokhranyaet_shift_i_masku(): void
    {
        $this->zaregistrirovatAccount();

        $credentialId = $this->sozdatRekvizity('main', 'https://old.local', 'old-model', 'sk-old-7777');
        $shiftBefore = $this->syrayaZapis($credentialId)['api_key_cipher'];

        $sut = self::$container->get(IzmenitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof IzmenitRekvizityOpenCodeUseCase);

        $sut->handle(new IzmenitRekvizityOpenCode(
            $credentialId,
            null,
            'https://new.local',
            'new-model',
            null,
        ));

        $row = $this->syrayaZapis($credentialId);

        $this->assertSame('https://new.local', $row['url']);
        $this->assertSame('new-model', $row['model']);
        $this->assertSame($shiftBefore, $row['api_key_cipher'], 'Без нового ключа шифротекст не меняется');
        $this->assertSame('****7777', $row['api_key_hint']);
    }

    #[Test]
    public function pustaya_stroka_klyucha_oznachaet_ostavit_prezhnii(): void
    {
        $this->zaregistrirovatAccount();

        $credentialId = $this->sozdatRekvizity('main', apiKey: 'sk-old-7777');
        $shiftBefore = $this->syrayaZapis($credentialId)['api_key_cipher'];

        $sut = self::$container->get(IzmenitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof IzmenitRekvizityOpenCodeUseCase);

        $sut->handle(new IzmenitRekvizityOpenCode($credentialId, null, null, null, ''));

        $this->assertSame($shiftBefore, $this->syrayaZapis($credentialId)['api_key_cipher']);
    }

    #[Test]
    public function izmenenie_klyucha_pereshifrovyvaet_i_obnovlyaet_masku(): void
    {
        $this->zaregistrirovatAccount();

        $credentialId = $this->sozdatRekvizity('main', apiKey: 'sk-old-7777');
        $shiftBefore = $this->syrayaZapis($credentialId)['api_key_cipher'];

        $sut = self::$container->get(IzmenitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof IzmenitRekvizityOpenCodeUseCase);

        $sut->handle(new IzmenitRekvizityOpenCode($credentialId, null, null, null, 'sk-new-4242'));

        $row = $this->syrayaZapis($credentialId);

        $this->assertNotSame($shiftBefore, $row['api_key_cipher']);
        $this->assertSame('****4242', $row['api_key_hint']);
    }

    #[Test]
    public function izmenenie_imeni_na_zanyatoe_zapreshcheno(): void
    {
        $this->zaregistrirovatAccount();

        $this->sozdatRekvizity('first');
        $secondId = $this->sozdatRekvizity('second');

        $sut = self::$container->get(IzmenitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof IzmenitRekvizityOpenCodeUseCase);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.name_double');

        $sut->handle(new IzmenitRekvizityOpenCode($secondId, 'first'));
    }

    #[Test]
    public function izmenenie_svoego_imeni_razresheno(): void
    {
        $this->zaregistrirovatAccount();

        $credentialId = $this->sozdatRekvizity('main');

        $sut = self::$container->get(IzmenitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof IzmenitRekvizityOpenCodeUseCase);

        // «То же» имя не должно считаться дублем самого себя.
        $sut->handle(new IzmenitRekvizityOpenCode($credentialId, 'main'));

        $this->assertSame('main', $this->syrayaZapis($credentialId)['name']);
    }

    #[Test]
    public function chuzhie_rekvizity_nelzya_izmenit(): void
    {
        $this->zaregistrirovatAccount();
        $alienId = $this->sozdatRekvizity('alien');

        $this->zaregistrirovatAccount();

        $sut = self::$container->get(IzmenitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof IzmenitRekvizityOpenCodeUseCase);

        $this->expectException(\DomainException::class);
        // Намеренно не раскрываем существование чужой записи.
        $this->expectExceptionMessage('account.opencode_credential.not_found');

        $sut->handle(new IzmenitRekvizityOpenCode($alienId, 'hijacked'));
    }

    #[Test]
    public function chuzhie_rekvizity_nelzya_udalit(): void
    {
        $this->zaregistrirovatAccount();
        $alienId = $this->sozdatRekvizity('alien');

        $this->zaregistrirovatAccount();

        $sut = self::$container->get(UdalitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof UdalitRekvizityOpenCodeUseCase);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.not_found');

        $sut->handle(new UdalitRekvizityOpenCode($alienId));
    }

    #[Test]
    public function udalenie_ubiraet_zapis_iz_spiska(): void
    {
        $this->zaregistrirovatAccount();

        $keepId = $this->sozdatRekvizity('keep');
        $dropId = $this->sozdatRekvizity('drop');

        $sut = self::$container->get(UdalitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof UdalitRekvizityOpenCodeUseCase);

        $sut->handle(new UdalitRekvizityOpenCode($dropId));

        $list = self::$container->get(PoluchitSpisokRekvizitovOpenCodeQuery::class);
        \assert($list instanceof PoluchitSpisokRekvizitovOpenCodeQuery);

        $this->assertSame([$keepId], array_column($list->handle(), 'id'));
    }

    #[Test]
    public function pustoi_id_pri_izmenenii_zapreshchen(): void
    {
        $this->zaregistrirovatAccount();

        $sut = self::$container->get(IzmenitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof IzmenitRekvizityOpenCodeUseCase);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.empty_id');

        $sut->handle(new IzmenitRekvizityOpenCode('   '));
    }

    #[Test]
    public function pustoi_id_pri_udalenii_zapreshchen(): void
    {
        $this->zaregistrirovatAccount();

        $sut = self::$container->get(UdalitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof UdalitRekvizityOpenCodeUseCase);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.empty_id');

        $sut->handle(new UdalitRekvizityOpenCode(''));
    }

    #[Test]
    public function udalenie_neizvestnogo_id_daet_not_found(): void
    {
        $this->zaregistrirovatAccount();

        $sut = self::$container->get(UdalitRekvizityOpenCodeUseCase::class);
        \assert($sut instanceof UdalitRekvizityOpenCodeUseCase);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.not_found');

        $sut->handle(new UdalitRekvizityOpenCode('no-such-credential'));
    }

    #[Test]
    public function api_rasshifrovyvaet_klyuch_dlya_vladelca(): void
    {
        $accountId = $this->zaregistrirovatAccount();

        $credentialId = $this->sozdatRekvizity('main', 'https://a.local', 'model-a', 'sk-api-5566');

        $api = self::$container->get(AccountApi::class);
        \assert($api instanceof AccountApi);

        $secret = $api->poluchitRekvizityOpenCodeDlyVladelca($accountId, $credentialId);

        $this->assertSame('sk-api-5566', $secret->apiKey, 'Ключ должен расшифровываться для владельца');
        $this->assertSame('https://a.local', $secret->url);
        $this->assertSame('model-a', $secret->model);
        $this->assertSame($accountId, $secret->accountId);
    }

    #[Test]
    public function api_ne_rasshifrovyvaet_klyuch_chuzhomu(): void
    {
        $this->zaregistrirovatAccount();
        $credentialId = $this->sozdatRekvizity('main', apiKey: 'sk-api-5566');

        $alienAccountId = $this->zaregistrirovatAccount();

        $api = self::$container->get(AccountApi::class);
        \assert($api instanceof AccountApi);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.not_found');

        // Владение проверяется ДО расшифровки: чужой ключ не покидает модуль.
        $api->poluchitRekvizityOpenCodeDlyVladelca($alienAccountId, $credentialId);
    }

    #[Test]
    public function proverka_vladeniya_ne_rasshifrovyvaet_klyuch(): void
    {
        $accountId = $this->zaregistrirovatAccount();
        $credentialId = $this->sozdatRekvizity('main');

        $api = self::$container->get(AccountApi::class);
        \assert($api instanceof AccountApi);

        $api->trebovatRekvizityOpenCodeVladelca($accountId, $credentialId);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.not_found');

        $api->trebovatRekvizityOpenCodeVladelca('other-account', $credentialId);
    }
}
