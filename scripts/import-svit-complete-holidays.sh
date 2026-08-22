#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

scripts=(
  "scripts/import-svit-holidays.php"
  "scripts/import-svit-calendar-articles.php"
  "scripts/import-svit-calendar-texts.php"
  "scripts/import-svit-calendar-fulltext.php"
  "scripts/import-svit-calendar-date-fulltext.php"
  "scripts/repair-holiday-content.php"
  "scripts/import-svit-calendar-pages.php"
  "scripts/clean-holiday-content.php"
)

for script in "${scripts[@]}"; do
  if [[ ! -f "$script" ]]; then
    echo "[FATAL] Не знайдено $script" >&2
    exit 1
  fi

  echo
  echo "=== $script ==="
  php "$script"
done

echo
echo "Повний імпорт свят завершено."
echo "Звіти:"
echo "- storage/app/content/holidays-import.json"
echo "- storage/app/content/holidays-calendar-articles.json"
echo "- storage/app/content/holidays-calendar-texts.json"
echo "- storage/app/content/holidays-calendar-fulltext.json"
echo "- storage/app/content/holidays-calendar-date-fulltext.json"
echo "- storage/app/content/holidays-calendar-pages.json"
echo "- storage/app/content/holidays-repair.json"
echo "- storage/app/content/holidays-cleanup.json"
