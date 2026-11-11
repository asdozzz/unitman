<?php

declare(strict_types=1);

namespace App\Utils\Tests\Service;

use App\Utils\Service\ExceptionTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class ExceptionTranslatorTest extends TestCase
{
    private const MESSAGES = [
        'en' => [
            'unit' => [
                'not_found' => 'Unit not found',
                'only_in_canon' => 'Canonical only message',
            ],
            'common' => [
                'internal_error' => 'An internal error occurred',
            ],
        ],
        'ru' => [
            'unit' => [
                'not_found' => 'Юнит не найден',
            ],
            'common' => [
                'internal_error' => 'Внутренняя ошибка',
            ],
        ],
    ];

    public function test_translates_key_into_requested_locale(): void
    {
        $translator = $this->makeTranslator('en');

        self::assertSame('Юнит не найден', $translator->translate('unit.not_found', 'ru'));
        self::assertSame('Unit not found', $translator->translate('unit.not_found', 'en'));
    }

    public function test_unknown_key_falls_back_to_canonical_dictionary(): void
    {
        $translator = $this->makeTranslator('en');

        // The key exists only in the canonical (en) dictionary.
        self::assertSame('Canonical only message', $translator->translate('unit.only_in_canon', 'ru'));
    }

    public function test_completely_unknown_key_is_returned_as_is(): void
    {
        $translator = $this->makeTranslator('en');

        self::assertSame('unit.unknown_key', $translator->translate('unit.unknown_key', 'ru'));
    }

    public function test_free_form_message_is_returned_untouched(): void
    {
        $translator = $this->makeTranslator('en');

        self::assertSame(
            'SQLSTATE[08006] connection failure',
            $translator->translate('SQLSTATE[08006] connection failure', 'ru'),
        );
    }

    public function test_key_with_appended_detail_keeps_the_detail(): void
    {
        $translator = $this->makeTranslator('en');

        self::assertSame(
            'Юнит не найден: 42',
            $translator->translate('unit.not_found: 42', 'ru'),
        );
    }

    public function test_unsupported_locale_is_normalised_to_canon(): void
    {
        $translator = $this->makeTranslator('en');

        self::assertSame('Unit not found', $translator->translate('unit.not_found', 'de-DE'));
    }

    public function test_regional_locale_uses_primary_subtag(): void
    {
        $translator = $this->makeTranslator('en');

        self::assertSame('Юнит не найден', $translator->translate('unit.not_found', 'ru_RU'));
    }

    public function test_locale_comes_from_request_stack_when_not_passed(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setLocale('ru');
        $requestStack->push($request);

        $translator = new ExceptionTranslator($this->translator(), 'en', $requestStack);

        self::assertSame('Юнит не найден', $translator->translate('unit.not_found'));
    }

    public function test_translate_throwable_translates_the_message(): void
    {
        $translator = $this->makeTranslator('en');

        self::assertSame(
            'Юнит не найден',
            $translator->translateThrowable(new \DomainException('unit.not_found'), 'ru'),
        );
    }

    public function test_translate_throwable_uses_internal_error_for_empty_message(): void
    {
        $translator = $this->makeTranslator('en');

        self::assertSame(
            'Внутренняя ошибка',
            $translator->translateThrowable(new \RuntimeException(''), 'ru'),
        );
    }

    private function makeTranslator(string $canon): ExceptionTranslator
    {
        return new ExceptionTranslator($this->translator(), $canon);
    }

    private function translator(): Translator
    {
        $translator = new Translator('en', new MessageFormatter());
        $translator->addLoader('array', new ArrayLoader());

        foreach (self::MESSAGES as $locale => $messages) {
            $translator->addResource('array', $messages, $locale);
        }

        return $translator;
    }
}
