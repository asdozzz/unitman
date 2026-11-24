<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\UseCase;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Infra\Controller\RunnerController;
use PHPUnit\Framework\TestCase;

/**
 * Гарантирует, что legacy default-runner удалён из контрактов, контроллера
 * и файловой структуры модуля.
 *
 * Миграция `Version20240415170449` (вне зоны ответственности этой подзадачи)
 * всё ещё ссылается на удалённый use case — это отдельный cross-scope долг,
 * который должен закрыть владелец миграций.
 */
final class DefaultRunnerRemovalTest extends TestCase
{
    public function test_runner_state_has_no_default_runner_factory(): void
    {
        self::assertFalse(method_exists(RunnerState::class, 'sozdatDefoltniiRunner'));
    }

    public function test_runner_repository_has_no_default_runner_lookup(): void
    {
        self::assertFalse(method_exists(RunnerRepository::class, 'getDefaultRunnerState'));
    }

    public function test_default_runner_use_case_files_are_removed(): void
    {
        $useCaseDir = \dirname(__DIR__, 3) . '/Business/UseCase';

        self::assertFileDoesNotExist($useCaseDir . '/SozdatDefoltniiRunnerUseCase.php');
        self::assertFileDoesNotExist($useCaseDir . '/PoluchitResursiDefoltnogoRunneraQuery.php');
    }

    public function test_no_default_runner_controller_methods(): void
    {
        $controller = new \ReflectionClass(RunnerController::class);

        self::assertFalse($controller->hasMethod('getDefaultRunnerActive'));
        self::assertFalse($controller->hasMethod('resources'));
        self::assertFalse($controller->hasMethod('test'));
        self::assertTrue($controller->hasMethod('register'));
    }
}
