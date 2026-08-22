<?php

declare(strict_types=1);

/**
 * Full holiday import runner.
 * 1. Imports long holiday articles when the old site has separate pages.
 * 2. Fills every remaining holiday page with text from cal.htm.
 */

$root = dirname(__DIR__);
$php = PHP_BINARY ?: 'php';
$scripts = [
    'scripts/import-svit-holidays.php',
    'scripts/import-svit-calendar-texts.php',
];

foreach ($scripts as $script) {
    $path = $root.'/'.$script;

    if (!is_file($path)) {
        fwrite(STDERR, "[FATAL] Не знайдено {$script}\n");
        exit(1);
    }

    echo "\n=== {$script} ===\n";
    passthru(escapeshellarg($php).' '.escapeshellarg($path), $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "[FATAL] {$script} завершився з кодом {$exitCode}\n");
        exit($exitCode);
    }
}

echo "\nПовний імпорт свят завершено.\n";
echo "Звіти:\n";
echo "- storage/app/content/holidays-import.json\n";
echo "- storage/app/content/holidays-calendar-texts.json\n";
