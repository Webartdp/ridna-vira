<?php

declare(strict_types=1);

/**
 * Full holiday import runner.
 * Some shared hosting plans disable PHP process functions. When passthru() is
 * disabled, use scripts/import-svit-complete-holidays.sh from bash instead.
 */

if (!function_exists('passthru')) {
    fwrite(STDERR, "[FATAL] У цьому PHP вимкнено passthru().\n");
    fwrite(STDERR, "Запустіть повний імпорт через bash:\n");
    fwrite(STDERR, "bash scripts/import-svit-complete-holidays.sh\n");
    exit(1);
}

$root = dirname(__DIR__);
$php = PHP_BINARY ?: 'php';
$scripts = [
    'scripts/import-svit-holidays.php',
    'scripts/import-svit-calendar-articles.php',
    'scripts/import-svit-calendar-texts.php',
    'scripts/import-svit-calendar-fulltext.php',
    'scripts/import-svit-calendar-date-fulltext.php',
    'scripts/repair-holiday-content.php',
    'scripts/clean-holiday-content.php',
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
echo "- storage/app/content/holidays-calendar-articles.json\n";
echo "- storage/app/content/holidays-calendar-texts.json\n";
echo "- storage/app/content/holidays-calendar-fulltext.json\n";
echo "- storage/app/content/holidays-calendar-date-fulltext.json\n";
echo "- storage/app/content/holidays-repair.json\n";
echo "- storage/app/content/holidays-cleanup.json\n";
