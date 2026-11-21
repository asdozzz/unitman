<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Projection;

use App\Unitman\Infra\Projection\OcheredUnitovProjection;
use App\Unitman\Infra\Projection\SpisokUnitovProjection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Каждая sync-проекция на потоке Unit обязана либо обрабатывать событие,
 * либо явно перечислять его в getExceptionEvents(). Иначе диспетчеризация
 * через AbstractProjection::handle() падает с RuntimeException.
 *
 * Тест статический: сканирует события агрегата Unit и сверяет их с проекциями.
 */
final class UnitStreamProjectionsCoverageTest extends TestCase
{
    private const EVENT_NAMESPACE = 'App\\Unitman\\Business\\Model\\Unit\\Event';

    /**
     * @return array<string, class-string>
     */
    private function sobiratSobitiyaUnita(): array
    {
        $baseDir = dirname(__DIR__, 2).'/Business/Model/Unit/Event';

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr(
                $file->getPathname(),
                strlen($baseDir) + 1,
                -strlen('.php')
            );

            $relativeClass = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
            $fqcn = self::EVENT_NAMESPACE.'\\'.$relativeClass;

            if (!class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);
            if (!$reflection->isInstantiable()) {
                continue;
            }

            $shortName = $reflection->getShortName();
            $files[$shortName] = $fqcn;
        }

        ksort($files);

        return $files;
    }

    /**
     * @return array<string, class-string>
     */
    private function sobratObrabativaemieSobitiya(string $projectionClass): array
    {
        $reflection = new ReflectionClass($projectionClass);
        $projection = $reflection->newInstanceWithoutConstructor();

        $method = new ReflectionMethod($projectionClass, 'getExceptionEvents');
        /** @var array<class-string> $exceptionEvents */
        $exceptionEvents = $method->invoke($projection);

        $handled = [];
        foreach ($exceptionEvents as $eventClass) {
            $handled[$eventClass] = $eventClass;
        }

        return $handled;
    }

    #[Test]
    public function vse_sobitiya_unita_obrabativayutsya_proekciyami_potoka(): void
    {
        $events = $this->sobiratSobitiyaUnita();
        self::assertNotEmpty($events, 'Не найдено ни одного события агрегата Unit');

        $projections = [
            OcheredUnitovProjection::class,
            SpisokUnitovProjection::class,
        ];

        foreach ($projections as $projectionClass) {
            $reflection = new ReflectionClass($projectionClass);
            $exceptionEvents = $this->sobratObrabativaemieSobitiya($projectionClass);

            foreach ($events as $shortName => $eventClass) {
                $handler = 'handle'.$shortName;

                if (method_exists($projectionClass, $handler)) {
                    continue;
                }

                if (isset($exceptionEvents[$eventClass])) {
                    continue;
                }

                self::fail(sprintf(
                    'Событие %s не обрабатывается проекцией %s: нет метода %s() и нет записи в getExceptionEvents()',
                    $eventClass,
                    $projectionClass,
                    $handler
                ));
            }
        }
    }
}
