<?php

declare(strict_types=1);

/**
 * Full holiday import runner.
 * 1. Imports long holiday articles when the old site has separate pages.
 * 2. Imports full articles by following the exact holiday links from cal.htm.
 * 3. Fills every remaining holiday page with short text from cal.htm.
 * 4. Forces full text from every linked cal.htm article over short snippets.
 * 5. Repairs empty/mojibake leftovers and removes duplicate imported headings.
 */

$root = dirname(__DIR__);
$php = PHP_BINARY ?: 'php';
$scripts = [
    'scripts/import-svit-holidays.php',
    'scripts/import-svit-calendar-articles.php',
    'scripts/import-svit-calendar-texts.php',
    'scripts/import-svit-calendar-fulltext.php',
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
echo "- storage/app/content/holidays-repair.json\n";
echo "- storage/app/content/holidays-cleanup.json\n";
