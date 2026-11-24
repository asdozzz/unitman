<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\Utils;

use App\Runner\Business\Utils\RunnerPublicAddressValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RunnerPublicAddressValidatorTest extends TestCase
{
    public function test_accepts_absolute_http_and_https_urls(): void
    {
        $validator = new RunnerPublicAddressValidator();

        self::assertSame('https://runner-1.example.com', $validator->validate('https://runner-1.example.com'));
        self::assertSame('http://runner-1.example.com:8080', $validator->validate('http://runner-1.example.com:8080'));
        self::assertSame(
            'https://runner-1.example.com',
            $validator->validate('  https://runner-1.example.com/  '),
            'хвостовой слэш и пробелы нормализуются',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'relative' => ['runner-1.example.com'];
        yield 'no scheme' => ['//runner-1.example.com'];
        yield 'empty' => ['   '];
        yield 'ftp scheme' => ['ftp://runner-1.example.com'];
        yield 'empty host' => ['https://'];
        yield 'userinfo' => ['https://user:pass@runner-1.example.com'];
        yield 'user only' => ['https://user@runner-1.example.com'];
        yield 'whitespace inside' => ['https://runner 1.example.com'];
    }

    #[DataProvider('invalidAddresses')]
    public function test_rejects_invalid_addresses(string $publicAddress): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(RunnerPublicAddressValidator::INVALID_CODE);

        (new RunnerPublicAddressValidator())->validate($publicAddress);
    }
}
