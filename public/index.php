<?php

use App\Kernel;

// RoadRunner считает любой вывод в STDOUT нарушением протокола воркера.
// Предупреждения Composer/PHP (например, из сгенерированных файлов safe)
// должны уходить в STDERR, иначе пул воркеров падает с stdout-crc.
ini_set('display_errors', 'stderr');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';
require_once __DIR__.'/functions.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
